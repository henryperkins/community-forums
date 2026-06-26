<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Security\BoardPolicy;

/**
 * Home: the category/board index (pane 1 + 2 of the three-pane shell). Hidden
 * boards are not listed; private boards appear only for an admin.
 */
final class HomeController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $user = $this->currentUser();
        $policy = $this->container->get(BoardPolicy::class);
        $categories = $this->container->get(CategoryRepository::class)->all();
        $allBoards = $this->container->get(BoardRepository::class)->allOrdered();

        $sections = [];
        foreach ($categories as $category) {
            $boards = array_values(array_filter(
                $allBoards,
                fn (array $b): bool => (int) $b['category_id'] === (int) $category['id']
                    && $policy->isListed($b, $user),
            ));
            $sections[] = ['category' => $category, 'boards' => $boards];
        }

        return $this->view('home', ['sections' => $sections]);
    }
}
