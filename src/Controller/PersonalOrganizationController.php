<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\PersonalOrganizationService;

final class PersonalOrganizationController extends Controller
{
    public function createFolder(Request $request, array $params): Response
    {
        $this->requireFlag('board_folders');
        $this->container->get(PersonalOrganizationService::class)->createFolder($this->requireUser(), $request->str('name'));
        return $this->redirectWithFlash('/settings/boards', 'Folder saved.');
    }

    public function addBoard(Request $request, array $params): Response
    {
        $this->requireFlag('board_folders');
        $this->container->get(PersonalOrganizationService::class)->addBoardToFolder(
            $this->requireUser(),
            (int) ($params['id'] ?? 0),
            (int) $request->int('board_id', 0),
        );
        return $this->redirectWithFlash('/settings/boards', 'Board added to folder.');
    }

    public function createSavedFeed(Request $request, array $params): Response
    {
        $this->requireFlag('saved_feeds');
        $this->container->get(PersonalOrganizationService::class)->createSavedFeed($this->requireUser(), $request->allInput());
        return $this->redirectWithFlash('/settings/boards', 'Saved feed created.');
    }

    private function requireFlag(string $flag): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled($flag)) {
            throw new NotFoundException('Not found.');
        }
    }
}
