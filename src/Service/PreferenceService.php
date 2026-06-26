<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserPreferenceRepository;

/**
 * Reading + appearance preferences (USER §4.1–§4.2, P2-10). Stored in the
 * per-user JSON blob. Server-enforced prefs (pagination, sort) are read back here
 * and shape queries so they hold across devices; client-only prefs (theme,
 * density, display toggles) are merely persisted for the browser. Every value is
 * validated against a fixed allow-list so a tampered blob can never inject SQL or
 * an out-of-range page size.
 */
final class PreferenceService
{
    private const THREADS_PER_PAGE = [25, 50, 100];
    private const POSTS_PER_PAGE = [10, 20, 40];
    private const THREAD_SORT = ['last_post', 'newest', 'replies'];
    private const THEME = ['system', 'light', 'dark'];
    private const DENSITY = ['comfortable', 'compact'];

    public function __construct(
        private UserPreferenceRepository $prefs,
        private int $defaultThreadsPerPage = 20,
        private int $defaultPostsPerPage = 20,
    ) {
    }

    /** @return array<string,mixed> the stored reading/appearance prefs (raw) */
    public function forUser(int $userId): array
    {
        return $this->prefs->get($userId);
    }

    public function threadsPerPage(int $userId): int
    {
        $v = (int) ($this->prefs->get($userId)['threads_per_page'] ?? 0);
        return in_array($v, self::THREADS_PER_PAGE, true) ? $v : $this->defaultThreadsPerPage;
    }

    public function postsPerPage(int $userId): int
    {
        $v = (int) ($this->prefs->get($userId)['posts_per_page'] ?? 0);
        return in_array($v, self::POSTS_PER_PAGE, true) ? $v : $this->defaultPostsPerPage;
    }

    /**
     * Validate + persist a reading/appearance preference update. Unknown or
     * out-of-range values are dropped (not stored), so the blob stays clean.
     *
     * @param array<string,mixed> $input
     */
    public function update(int $userId, array $input): void
    {
        $changes = [];
        $changes['threads_per_page'] = $this->oneOfInt($input['threads_per_page'] ?? null, self::THREADS_PER_PAGE);
        $changes['posts_per_page'] = $this->oneOfInt($input['posts_per_page'] ?? null, self::POSTS_PER_PAGE);
        $changes['thread_sort'] = $this->oneOf($input['thread_sort'] ?? null, self::THREAD_SORT);
        $changes['theme'] = $this->oneOf($input['theme'] ?? null, self::THEME);
        $changes['density'] = $this->oneOf($input['density'] ?? null, self::DENSITY);
        $changes['show_signatures'] = $this->boolOrNull($input, 'show_signatures');
        $changes['show_avatars'] = $this->boolOrNull($input, 'show_avatars');
        $changes['show_reactions'] = $this->boolOrNull($input, 'show_reactions');

        $this->prefs->merge($userId, $changes);
    }

    /** @param list<int> $allowed */
    private function oneOfInt(mixed $value, array $allowed): ?int
    {
        $v = (int) $value;
        return in_array($v, $allowed, true) ? $v : null;
    }

    /** @param list<string> $allowed */
    private function oneOf(mixed $value, array $allowed): ?string
    {
        $v = is_string($value) ? $value : '';
        return in_array($v, $allowed, true) ? $v : null;
    }

    /** A submitted checkbox is true when present; this form always submits all toggles. */
    private function boolOrNull(array $input, string $key): bool
    {
        return array_key_exists($key, $input) && (string) $input[$key] !== '0' && $input[$key] !== false;
    }
}
