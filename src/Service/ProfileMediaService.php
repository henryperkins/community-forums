<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\AttachmentRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

final class ProfileMediaService
{
    public function __construct(
        private Database $db,
        private AttachmentService $attachments,
        private AttachmentRepository $attachmentRepo,
        private UserRepository $users,
        private WriteGate $writeGate,
    ) {
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $file */
    public function uploadAvatar(User $user, ?array $file): void
    {
        $this->writeGate->assertCanWrite($user);
        if ($file === null) {
            throw new ValidationException(['avatar' => 'Choose an avatar image.']);
        }

        $row = $this->attachments->storeUpload($user->id(), $file, 'avatar');
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || !$this->attachmentRepo->finalizeBrandAsset($id, $user->id())) {
            throw new ValidationException(['avatar' => 'The avatar could not be saved.']);
        }

        $this->users->setAvatar($user->id(), '/media/' . $id, 'upload');
    }

    public function removeAvatar(User $user): void
    {
        $this->writeGate->assertCanWrite($user);
        $row = $this->users->find($user->id()) ?? [];
        $avatarPath = isset($row['avatar_path']) ? (string) $row['avatar_path'] : '';

        $this->db->transaction(function () use ($user, $avatarPath): void {
            $this->deleteLocalAvatar($avatarPath);
            $this->users->setAvatar($user->id(), null, 'monogram', $user->id());
        });
    }

    private function deleteLocalAvatar(string $path): void
    {
        if (preg_match('~^/media/(\d+)$~', $path, $matches) !== 1) {
            return;
        }

        $this->attachmentRepo->markDeleted((int) $matches[1]);
    }
}
