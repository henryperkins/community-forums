<?php

declare(strict_types=1);
namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\PersonalOrganizationService;

final class PersonalOrganizationController extends Controller
{
    private function mutate(Request $request, string $flag, string $form, callable $operation, string $message): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled($flag)) { throw new NotFoundException('Not found.'); }
        $user = $this->requireUser();
        try { $operation($this->container->get(PersonalOrganizationService::class), $user); }
        catch (ValidationException $e) {
            return (new SettingsController($this->container))->boardsView($user, [
                'org_form' => $form, 'org_errors' => $e->errors,
                'org_old' => array_filter($e->old + $request->allInput(), static fn ($value, $key): bool => is_scalar($value) || ($key === 'board_ids' && is_array($value) && count(array_filter($value, 'is_scalar')) === count($value)), ARRAY_FILTER_USE_BOTH),
            ], 422);
        }
        return $this->redirectWithFlash('/settings/boards', $message);
    }

    public function createFolder(Request $r, array $p): Response
    {
        return $this->mutate($r, 'board_folders', 'folder-create', fn ($s, $u) => $s->createFolder($u, $r->str('name')), 'Folder saved.');
    }

    public function renameFolder(Request $r, array $p): Response
    {
        return $this->mutate($r, 'board_folders', 'folder-' . $p['id'], fn ($s, $u) => $s->renameFolder($u, (int) $p['id'], $r->str('name')), 'Folder renamed.');
    }

    public function deleteFolder(Request $r, array $p): Response
    {
        return $this->mutate($r, 'board_folders', 'folder-' . $p['id'], fn ($s, $u) => $s->deleteFolder($u, (int) $p['id']), 'Folder deleted.');
    }

    public function addBoard(Request $r, array $p): Response
    {
        $folderId = (int) ($p['id'] ?? 0) ?: $r->int('folder_id', 0);
        return $this->mutate($r, 'board_folders', 'folder-add', fn ($s, $u) => $s->addBoardToFolder($u, $folderId, $r->int('board_id', 0)), 'Board added to folder.');
    }

    public function removeBoard(Request $r, array $p): Response
    {
        if (!ctype_digit((string) ($p['board_id'] ?? '')) || (int) $p['board_id'] <= 0) { throw new NotFoundException('Not found.'); }
        return $this->mutate($r, 'board_folders', 'folder-' . $p['id'], fn ($s, $u) => $s->removeBoardFromFolder($u, (int) $p['id'], (int) $p['board_id']), 'Board removed from folder.');
    }

    public function createSavedFeed(Request $r, array $p): Response
    {
        return $this->mutate($r, 'saved_feeds', 'feed-create', fn ($s, $u) => $s->createSavedFeed($u, $r->allInput()), 'Saved feed created.');
    }

    public function updateSavedFeed(Request $r, array $p): Response
    {
        return $this->mutate($r, 'saved_feeds', 'feed-' . $p['id'], fn ($s, $u) => $s->updateSavedFeed($u, (int) $p['id'], $r->allInput()), 'Saved feed updated.');
    }

    public function deleteSavedFeed(Request $r, array $p): Response
    {
        return $this->mutate($r, 'saved_feeds', 'feed-' . $p['id'], fn ($s, $u) => $s->deleteSavedFeed($u, (int) $p['id']), 'Saved feed deleted.');
    }

    public function createBookmarkFolder(Request $r, array $p): Response
    {
        return $this->mutate($r, 'bookmark_folders', 'bookmark-create', fn ($s, $u) => $s->createBookmarkFolder($u, $r->str('name')), 'Bookmark folder saved.');
    }

    public function addThreadToBookmarkFolder(Request $r, array $p): Response
    {
        return $this->mutate($r, 'bookmark_folders', 'bookmark-add', fn ($s, $u) => $s->addThreadToBookmarkFolder($u, (int) ($p['id'] ?? $r->int('folder_id', 0)), $r->int('thread_id', 0)), 'Thread added to bookmark folder.');
    }
}
