<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;

/**
 * Central, reviewable anti-abuse automation (P3-05, ADMIN §10.2, DECISIONS #11).
 *
 * evaluate() scores a candidate submission against word/link/duplicate/flood
 * rules plus new-user throttles and returns an {@see AntiAbuseDecision}. The
 * natural severity is clamped to the operator's configured `mode` so a fresh
 * install (observe) never holds or blocks legitimate content; an operator opts
 * up to flag → hold → block after reviewing false positives. Rules allow, flag,
 * hold, or block — destructive/punitive automation is never the default.
 *
 * audit() writes an immutable moderation_log row with a NULL (system) actor, the
 * rule, the reason, the mode, and the natural-vs-effective action, so every
 * automated action is reviewable (PHASE_3_PLAN §9 "Automated audit").
 */
final class AntiAbuseService
{
    private const SEVERITY = ['allow' => 0, 'flag' => 1, 'hold' => 2, 'block' => 3];
    private const ACTION = ['allow', 'flag', 'hold', 'block'];

    public function __construct(
        private Database $db,
        private Config $config,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
    ) {
    }

    /**
     * @param 'thread'|'reply'|'dm' $context
     */
    public function evaluate(User $user, string $context, string $body, ?string $title = null): AntiAbuseDecision
    {
        // Staff are trusted; never throttle/hold them (role exemption, not a
        // reputation exemption — reputation never grants powers, COMMUNITY §1).
        if ($user->isModerator()) {
            return AntiAbuseDecision::allow();
        }

        $cfg = (array) $this->config->get('antiabuse', []);
        $mode = $this->mode();
        $text = trim(($title ?? '') . "\n" . $body);
        $newUser = $this->isNewUser($user, $cfg);

        $severity = 0;
        $reasons = [];
        $rule = '';

        // 1. Blocked words → block-worthy.
        $blocked = $this->matchedBlockedWord($text);
        if ($blocked !== null) {
            $severity = max($severity, self::SEVERITY['block']);
            $reasons[] = 'blocked term';
            $rule = 'blocked_word';
        }

        // 2. Excessive links → hold-worthy (stricter ceiling for new users).
        $links = $this->countLinks($body);
        $linkMax = $newUser ? (int) ($cfg['new_user_max_links'] ?? 2) : (int) ($cfg['max_links'] ?? 25);
        if ($links > $linkMax) {
            $severity = max($severity, self::SEVERITY['hold']);
            $reasons[] = "too many links ($links > $linkMax)";
            $rule = $rule ?: 'excessive_links';
        }

        // 3. Duplicate body by the same user in a recent window → hold-worthy.
        if ($context !== 'dm' && $this->isDuplicate($user->id(), $body, (int) ($cfg['duplicate_window_seconds'] ?? 3600))) {
            $severity = max($severity, self::SEVERITY['hold']);
            $reasons[] = 'duplicate of a recent post';
            $rule = $rule ?: 'duplicate';
        }

        // 4. Posting flood by the same user in a short window → hold-worthy.
        if ($context !== 'dm') {
            $window = (int) ($cfg['flood_window_seconds'] ?? 60);
            $max = (int) ($cfg['flood_max_posts'] ?? 10);
            if ($max > 0 && $this->recentPostCount($user->id(), $window) >= $max) {
                $severity = max($severity, self::SEVERITY['hold']);
                $reasons[] = 'posting too quickly';
                $rule = $rule ?: 'flood';
            }
        }

        $natural = self::ACTION[$severity];
        $ceiling = self::SEVERITY[$mode] ?? 0;
        $effective = self::ACTION[min($severity, $ceiling)];

        return new AntiAbuseDecision($effective, $natural, $reasons, $mode, $rule);
    }

    /**
     * Record an automated decision in the immutable audit trail (system actor).
     * Called after the content is created (flag/hold/observe) or rejected
     * (block, target_id 0). Only writes when a rule actually fired.
     */
    public function audit(AntiAbuseDecision $decision, string $targetType, int $targetId): void
    {
        if (!$decision->triggered()) {
            return;
        }
        $this->log->log([
            'actor_id' => null, // system actor
            'action' => 'auto_' . $decision->action, // auto_allow|auto_flag|auto_hold|auto_block
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => $decision->reasonText(),
            'before' => null,
            'after' => [
                'rule' => $decision->rule,
                'mode' => $decision->mode,
                'natural' => $decision->natural,
                'effective' => $decision->action,
            ],
        ]);
    }

    /** Current enforcement mode: a `settings` override wins over config. */
    public function mode(): string
    {
        $override = $this->settings->getString('antiabuse_mode', '');
        $mode = $override !== '' ? $override : (string) $this->config->get('antiabuse.mode', 'observe');
        return isset(self::SEVERITY[$mode]) && $mode !== 'allow' ? $mode : 'observe';
    }

    /** @param array<string,mixed> $cfg */
    private function isNewUser(User $user, array $cfg): bool
    {
        $row = $user->toArray();
        $minPosts = (int) ($cfg['new_user_min_posts'] ?? 3);
        if ((int) ($row['post_count'] ?? 0) >= $minPosts) {
            return false;
        }
        $minAge = (int) ($cfg['new_user_min_age_minutes'] ?? 0) * 60;
        if ($minAge > 0) {
            $created = strtotime((string) ($row['created_at'] ?? '') . ' UTC');
            if ($created !== false && (time() - $created) >= $minAge) {
                return false;
            }
        }
        return true;
    }

    private function matchedBlockedWord(string $text): ?string
    {
        $words = (array) $this->config->get('antiabuse.blocked_words', []);
        $extra = $this->settings->get('antiabuse_blocked_words', []);
        if (is_array($extra)) {
            $words = array_merge($words, $extra);
        }
        $haystack = mb_strtolower($text);
        foreach ($words as $word) {
            $w = mb_strtolower(trim((string) $word));
            if ($w !== '' && str_contains($haystack, $w)) {
                return $w;
            }
        }
        return null;
    }

    private function countLinks(string $body): int
    {
        return preg_match_all('~\bhttps?://~i', $body) ?: 0;
    }

    private function isDuplicate(int $userId, string $body, int $windowSeconds): bool
    {
        $trimmed = trim($body);
        if ($trimmed === '' || $windowSeconds <= 0) {
            return false;
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        return $this->db->fetchValue(
            'SELECT 1 FROM posts WHERE user_id = ? AND is_deleted = 0 AND body = ? AND created_at >= ? LIMIT 1',
            [$userId, $body, $cutoff],
        ) !== false;
    }

    private function recentPostCount(int $userId, int $windowSeconds): int
    {
        if ($windowSeconds <= 0) {
            return 0;
        }
        $cutoff = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM posts WHERE user_id = ? AND created_at >= ?',
            [$userId, $cutoff],
        );
    }
}
