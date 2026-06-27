<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\Session;

/**
 * First-run setup wizard (ADMIN §9). Creates the first administrator, the
 * community name, and a starter set of categories/boards — all in application
 * code (Phase 1 has no seed migration) — then signs the admin in. Once an admin
 * exists the install is "initialized" and the wizard locks.
 */
final class SetupService
{
    /** @var array<int, array{name:string, boards:array<int,array{name:string,slug:string,description:string}>}> */
    private const STARTER = [
        [
            'name' => 'General',
            'boards' => [
                ['name' => 'Announcements', 'slug' => 'announcements', 'description' => 'News and updates from the team.'],
                ['name' => 'General Discussion', 'slug' => 'general', 'description' => 'Talk about anything here.'],
            ],
        ],
        [
            'name' => 'Off-Topic',
            'boards' => [
                ['name' => 'The Lounge', 'slug' => 'lounge', 'description' => 'Hang out and chat.'],
            ],
        ],
    ];

    public function __construct(
        private Database $db,
        private AuthService $auth,
        private UserRepository $users,
        private SettingRepository $settings,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private ModerationLogRepository $log,
        private Session $session,
    ) {
    }

    public function isInitialized(): bool
    {
        return $this->users->adminCount() > 0;
    }

    /** @param array<string,mixed> $input */
    public function run(array $input): User
    {
        if ($this->isInitialized()) {
            throw new ForbiddenException('Setup has already been completed.');
        }

        $siteName = trim((string) ($input['site_name'] ?? ''));
        if ($siteName === '' || mb_strlen($siteName) > 80) {
            throw new ValidationException(['site_name' => 'Enter a community name (1–80 characters).'], $input);
        }

        $admin = $this->db->transaction(function () use ($input, $siteName): User {
            $admin = $this->auth->register($input, 'admin');
            // The site operator owns the install — no email round-trip needed.
            $this->users->markEmailVerified($admin->id());

            $this->settings->set('site_name', $siteName);
            $this->settings->set('registration_mode', 'open');
            $this->settings->set('installed_at', gmdate('Y-m-d H:i:s'));

            $this->seedStarterContent();

            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'install',
                'target_type' => 'setting',
                'target_id' => 0,
                'after' => ['site_name' => $siteName],
            ]);

            return $admin;
        });

        $this->session->login($admin);

        return $admin;
    }

    private function seedStarterContent(): void
    {
        $position = 0;
        foreach (self::STARTER as $category) {
            $categoryId = $this->categories->create($category['name'], $position++);
            $boardPosition = 0;
            foreach ($category['boards'] as $board) {
                $this->boards->create([
                    'category_id' => $categoryId,
                    'slug' => $board['slug'],
                    'name' => $board['name'],
                    'description' => $board['description'],
                    'position' => $boardPosition++,
                    'visibility' => 'public',
                    'post_min_role' => 'user',
                ]);
            }
        }
    }
}
