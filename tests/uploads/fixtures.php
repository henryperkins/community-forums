<?php

declare(strict_types=1);

// Deterministic synthetic input, emitted to stdout for Playwright's in-memory files.
$kind = $argv[1] ?? 'large';
$dimension = $kind === 'dimensions' ? 4097 : ($kind === 'large' ? 1024 : 16);
$image = imagecreatetruecolor($dimension, $kind === 'dimensions' ? 1 : $dimension);
ob_start();
match ($kind) {
    'jpeg' => imagejpeg($image, null, 85),
    'gif' => imagegif($image),
    'webp' => imagewebp($image),
    default => imagepng($image, null, $kind === 'large' ? 0 : 6),
};
$bytes = (string) ob_get_clean();
if ($kind === 'pixels') {
    // Valid PNG header declaring 25 million pixels. Intake must reject before GD allocation.
    $header = substr_replace(substr($bytes, 12, 17), pack('NN', 5000, 5000), 4, 8);
    $bytes = substr($bytes, 0, 12) . $header . pack('N', crc32($header)) . substr($bytes, 33);
}
if (in_array($kind, ['heic', 'avif'], true)) {
    // Recognizable ISO-BMFF brands; unsupported regardless of decoding support.
    $brand = $kind === 'heic' ? 'heic' : 'avif';
    $bytes = pack('N', 24) . 'ftyp' . $brand . pack('N', 0) . $brand . 'mif1';
}
if ($kind === 'large' && (strlen($bytes) <= 2097152 || strlen($bytes) >= 5242880)) {
    throw new RuntimeException('Fixture must be above 2 MiB and below 5 MiB.');
}
if ($kind === 'padded') {
    $size = (int) ($argv[2] ?? 0);
    if ($size < strlen($bytes) + 20 || $size > 12 * 1024 * 1024) {
        throw new RuntimeException('Invalid synthetic fixture size.');
    }
    $data = "Padding\0" . str_repeat('x', $size - strlen($bytes) - 20);
    $chunk = 'tEXt' . $data;
    $bytes = substr($bytes, 0, -12) . pack('N', strlen($data)) . $chunk . pack('N', crc32($chunk)) . substr($bytes, -12);
    if (strlen($bytes) !== $size || getimagesizefromstring($bytes) === false) {
        throw new RuntimeException('Invalid padded PNG.');
    }
}
echo $bytes;
