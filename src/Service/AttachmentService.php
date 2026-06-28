<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\ValidationException;
use App\Repository\AttachmentRepository;
use finfo;

/**
 * Safe image intake + storage (P3-04, PHASE_3_PLAN §9 image-spoofing/orphan/disk
 * scenarios). Every upload is:
 *  - size-capped before and after reading bytes;
 *  - content-sniffed (finfo + getimagesize), never trusting the client filename;
 *  - dimension-capped and decompression-bomb-guarded BEFORE decoding;
 *  - re-encoded through GD, which strips metadata and neutralises image/script
 *    polyglots (the stored bytes are produced by us, not the uploader);
 *  - written under a non-executable, non-public storage root with an unguessable
 *    key and a content sha256.
 *
 * Files are stored 'temp' and only bound (finalized) to a post/DM once that
 * parent commits; orphan temp files and files of deleted parents are reclaimed
 * by the sweep.
 */
final class AttachmentService
{
    /**
     * @param list<string> $allowedMime
     */
    public function __construct(
        private AttachmentRepository $repo,
        private string $storagePath,
        private int $maxBytes = 5_242_880,
        private int $maxWidth = 4096,
        private int $maxHeight = 4096,
        private int $maxPixels = 24_000_000,
        private array $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        private int $minFreeBytes = 0,
    ) {
    }

    /**
     * Validate, re-encode, and store an uploaded image as a temp attachment.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array<string,mixed> the stored attachment row
     */
    public function storeUpload(int $userId, array $file, string $purpose = 'post'): array
    {
        if ((int) $file['size'] > $this->maxBytes) {
            throw new ValidationException(['image' => 'That image is too large.']);
        }
        $bytes = @file_get_contents($file['tmp_name']);
        if ($bytes === false || $bytes === '') {
            throw new ValidationException(['image' => 'The upload could not be read.']);
        }
        if (strlen($bytes) > $this->maxBytes) {
            throw new ValidationException(['image' => 'That image is too large.']);
        }

        // Content sniff — the client-sent type and filename are never trusted.
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (!in_array($mime, $this->allowedMime, true)) {
            throw new ValidationException(['image' => 'Only JPEG, PNG, GIF, or WebP images are allowed.']);
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || !isset($info[0], $info[1])) {
            throw new ValidationException(['image' => 'That file is not a valid image.']);
        }
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        if ($w < 1 || $h < 1) {
            throw new ValidationException(['image' => 'That file is not a valid image.']);
        }
        // Decompression-bomb guard BEFORE allocating the bitmap.
        if ($w * $h > $this->maxPixels) {
            throw new ValidationException(['image' => 'That image has too many pixels.']);
        }
        if ($w > $this->maxWidth || $h > $this->maxHeight) {
            throw new ValidationException(['image' => "That image is too large (max {$this->maxWidth}×{$this->maxHeight})."]);
        }

        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            throw new ValidationException(['image' => 'That image could not be processed.']);
        }
        // (GD images are freed by the garbage collector; imagedestroy() is a
        // deprecated no-op since PHP 8.0.)
        [$out, $ext, $outMime] = $this->reencode($img, $mime);

        // The re-encoded bytes must also respect the size cap (a small input can
        // expand on re-encode); keeps on-disk size within the same ceiling.
        if (strlen($out) > $this->maxBytes) {
            throw new ValidationException(['image' => 'That image is too large after processing.']);
        }

        $sha = hash('sha256', $out);
        $key = $this->makeKey($ext);
        $this->writeFile($key, $out);

        $id = $this->repo->create([
            'user_id' => $userId,
            'purpose' => $purpose,
            'kind' => 'image',
            'status' => 'temp',
            'storage_key' => $key,
            'sha256' => $sha,
            'mime' => $outMime,
            'size_bytes' => strlen($out),
            'width' => $w,
            'height' => $h,
            'visibility' => 'public',
        ]);
        return $this->repo->find($id) ?? [];
    }

    /** Absolute path to an attachment's bytes (for authorization-gated delivery). */
    public function pathFor(array $attachment): string
    {
        return $this->storagePath . '/' . ltrim((string) $attachment['storage_key'], '/');
    }

    public function readBytes(array $attachment): ?string
    {
        $path = $this->pathFor($attachment);
        if (!is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);
        return $bytes === false ? null : $bytes;
    }

    /** Delete an attachment's file from disk and mark the row deleted. */
    public function purge(array $attachment): void
    {
        $path = $this->pathFor($attachment);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->repo->markDeleted((int) $attachment['id']);
    }

    /**
     * Extract the attachment ids a body references via /media/{id} (the only
     * image-src form the sanitizer permits), for finalize-on-publish.
     *
     * @return list<int>
     */
    public static function referencedIds(string $body): array
    {
        if (preg_match_all('~/media/(\d+)~', $body, $m) && isset($m[1])) {
            return array_values(array_unique(array_map('intval', $m[1])));
        }
        return [];
    }

    /** @return array{0:string,1:string,2:string} [bytes, ext, mime] */
    private function reencode(\GdImage $img, string $mime): array
    {
        ob_start();
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($img, null, 85);
                $ext = 'jpg';
                $outMime = 'image/jpeg';
                break;
            case 'image/gif':
                imagegif($img);
                $ext = 'gif';
                $outMime = 'image/gif';
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagewebp($img, null, 85);
                    $ext = 'webp';
                    $outMime = 'image/webp';
                    break;
                }
                // Fall through to PNG if the GD build lacks WebP.
            case 'image/png':
            default:
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagepng($img);
                $ext = 'png';
                $outMime = 'image/png';
                break;
        }
        $out = (string) ob_get_clean();
        return [$out, $ext, $outMime];
    }

    private function makeKey(string $ext): string
    {
        return gmdate('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private function writeFile(string $key, string $bytes): void
    {
        $path = $this->storagePath . '/' . $key;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if ($this->minFreeBytes > 0) {
            $free = @disk_free_space($dir);
            if ($free === false || $free < ($this->minFreeBytes + strlen($bytes))) {
                throw new ValidationException(['image' => 'The image store is temporarily full. Please try again later.']);
            }
        }
        if (@file_put_contents($path, $bytes) === false) {
            throw new ValidationException(['image' => 'The image could not be stored. Please try again.']);
        }
    }
}
