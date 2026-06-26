<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Support\Markdown;

/**
 * Public profile (/u/{username}): username/display name, monogram avatar, bio,
 * location, join date, post count, reputation (0 in Phase 1). Email is never
 * shown to anyone.
 */
final class ProfileController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $username = $params['username'] ?? '';
        $profile = $this->container->get(UserRepository::class)->findByUsername($username);
        if ($profile === null) {
            throw new NotFoundException('That member could not be found.');
        }

        $bioHtml = '';
        if (is_string($profile['bio'] ?? null) && $profile['bio'] !== '') {
            $bioHtml = $this->container->get(Markdown::class)->render((string) $profile['bio']);
        }

        $recentThreads = $this->container->get(ThreadRepository::class)->recentByUser((int) $profile['id'], 5);

        return $this->view('profile/show', [
            'profile' => $profile,
            'bio_html' => $bioHtml,
            'recent_threads' => $recentThreads,
        ]);
    }
}
