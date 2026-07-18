<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\RegistrationPolicy;
use App\Security\WriteGate;

/**
 * Owns the admin-facing settings pages and their narrowly scoped writes.
 * Each mutation validates and audits only the keys owned by its page.
 */
final class AdminSettingsService
{
    public const REGISTRATION_MODES = RegistrationPolicy::MODES;
    public const ANTI_ABUSE_MODES = ['observe', 'flag', 'hold', 'block'];

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private FeatureFlags $features,
    ) {
    }

    /**
     * @param array<string,mixed> $overlay
     * @return array<string,mixed>
     */
    public function generalModel(array $overlay = []): array
    {
        return array_replace([
            'site_name' => $this->settings->getString('site_name', 'RetroBoards'),
            'registration_mode' => $this->settings->getString('registration_mode', 'open'),
            'registration_modes' => self::REGISTRATION_MODES,
            'invitations_flag_on' => $this->features->enabled('invitations'),
            'settings_errors' => [],
            'settings_old' => [],
        ], $overlay);
    }

    /**
     * @param array<string,mixed> $overlay
     * @return array<string,mixed>
     */
    public function moderationModel(array $overlay = []): array
    {
        return array_replace([
            'antiabuse_mode' => $this->settings->getString('antiabuse_mode', 'observe'),
            'antiabuse_blocked_words' => (array) $this->settings->get('antiabuse_blocked_words', []),
            'antiabuse_modes' => self::ANTI_ABUSE_MODES,
            'settings_errors' => [],
            'settings_old' => [],
        ], $overlay);
    }

    public function updateSiteName(User $admin, string $name): void
    {
        $this->assertAdmin($admin);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(
                ['site_name' => 'Site name must be 1–80 characters.'],
                ['site_name' => $name],
            );
        }

        $before = $this->settings->getString('site_name', '');
        $this->db->transaction(function () use ($admin, $name, $before): void {
            $this->settings->set('site_name', $name);
            $this->audit($admin, 'site_name', ['site_name' => $before], ['site_name' => $name]);
        });
    }

    public function updateRegistration(User $admin, string $mode): void
    {
        $this->assertAdmin($admin);
        if (!in_array($mode, self::REGISTRATION_MODES, true)) {
            throw new ValidationException(
                ['registration_mode' => 'Unknown registration mode.'],
                ['registration_mode' => $mode],
            );
        }

        $before = $this->settings->getString('registration_mode', 'open');
        $this->db->transaction(function () use ($admin, $mode, $before): void {
            $this->settings->set('registration_mode', $mode);
            $this->audit(
                $admin,
                'registration_mode',
                ['registration_mode' => $before],
                ['registration_mode' => $mode],
            );
        });
    }

    /** @param array<string,mixed> $input */
    public function updateAntiAbuse(User $admin, array $input): void
    {
        $this->assertAdmin($admin);

        $mode = (string) ($input['antiabuse_mode'] ?? '');
        if (!in_array($mode, self::ANTI_ABUSE_MODES, true)) {
            throw new ValidationException(
                ['antiabuse_mode' => 'Unknown anti-abuse mode.'],
                $input,
            );
        }

        $raw = is_string($input['antiabuse_blocked_words'] ?? null)
            ? $input['antiabuse_blocked_words']
            : '';
        $words = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $word) {
            $word = trim((string) $word);
            $length = mb_strlen($word);
            if ($length >= AntiAbuseService::MIN_BLOCKED_WORD_LENGTH && $length <= 100) {
                $words[mb_strtolower($word)] = $word;
            }
        }
        $words = array_values($words);

        $before = [
            'antiabuse_mode' => $this->settings->getString('antiabuse_mode', 'observe'),
            'antiabuse_blocked_words' => (array) $this->settings->get('antiabuse_blocked_words', []),
        ];
        $after = [
            'antiabuse_mode' => $mode,
            'antiabuse_blocked_words' => $words,
        ];

        $this->db->transaction(function () use ($admin, $mode, $words, $before, $after): void {
            $this->settings->set('antiabuse_mode', $mode);
            $this->settings->set('antiabuse_blocked_words', $words);
            $this->audit($admin, 'anti_abuse_settings', $before, $after);
        });
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    private function audit(User $admin, string $reason, array $before, array $after): void
    {
        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => 'update_setting',
            'target_type' => 'setting',
            'target_id' => 0,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
        ]);
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        $this->writeGate->assertCanWrite($admin);
    }
}
