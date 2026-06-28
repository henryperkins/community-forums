<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;

/**
 * Browser-local Drafts view (P3-03). Draft bodies stay in localStorage; the
 * server only renders the authenticated shell that composer.js hydrates.
 */
final class DraftController extends Controller
{
    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('drafts')) {
            throw new NotFoundException('Not found.');
        }
        $this->requireUser();
        return $this->view('account/drafts');
    }
}
