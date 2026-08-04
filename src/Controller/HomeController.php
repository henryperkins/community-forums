<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\NavigationService;

/**
 * Home: the category/board index (pane 1 + 2 of the three-pane shell). Hidden
 * boards are not listed; private boards appear only for an admin.
 */
final class HomeController extends Controller
{
    /** @param array<string,string> $params */
    public function privacy(Request $request, array $params): Response
    {
        return $this->view('privacy');
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $sections = $this->container->get(NavigationService::class)->homeSections($this->currentUser());

        return $this->view('home', ['sections' => $sections]);
    }
}
