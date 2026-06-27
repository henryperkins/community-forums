<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserPreferenceRepository;
use App\Support\PreferenceSchema;

/**
 * Appearance / reading / composing preferences (USER §4, P3-01). Stored in the
 * per-user JSON blob and validated against {@see PreferenceSchema} so a tampered
 * or stale document can never inject SQL, an out-of-range page size, or break
 * rendering. Server-enforced prefs (pagination, sort) are read back here and
 * shape queries so they hold across devices; client prefs (theme, density,
 * font size, reduced motion, composing toggles) are persisted server-side too,
 * so the experience is consistent on every device.
 */
final class PreferenceService
{
    public function __construct(
        private UserPreferenceRepository $prefs,
        private int $defaultThreadsPerPage = 20,
        private int $defaultPostsPerPage = 20,
    ) {
    }

    /** @return array<string,mixed> the raw stored blob ({} when none) */
    public function forUser(int $userId): array
    {
        return $this->prefs->get($userId);
    }

    /**
     * Defaults merged with the user's validated overrides — safe for rendering
     * and for read-path decisions.
     *
     * @return array<string,mixed>
     */
    public function resolved(int $userId): array
    {
        return PreferenceSchema::resolve($this->prefs->get($userId));
    }

    /**
     * The appearance subset (theme/density/font_size/reduced_motion) used to
     * stamp the document root so there is no theme flash and no-JS still themes.
     *
     * @return array{theme:string,density:string,font_size:string,reduced_motion:bool}
     */
    public function appearance(int $userId): array
    {
        $r = $this->resolved($userId);
        return [
            'theme' => (string) $r['theme'],
            'density' => (string) $r['density'],
            'font_size' => (string) $r['font_size'],
            'reduced_motion' => (bool) $r['reduced_motion'],
        ];
    }

    /**
     * The reading-display subset (thread_sort + show_signatures/avatars/reactions)
     * the thread/board render paths consult so the toggles actually take effect
     * (P3-01). Values are already validated by {@see PreferenceSchema}.
     *
     * @return array{thread_sort:string,show_signatures:bool,show_avatars:bool,show_reactions:bool}
     */
    public function reading(int $userId): array
    {
        return $this->pickReading($this->resolved($userId));
    }

    /**
     * The same reading subset at schema defaults — used for guests, who have no
     * stored prefs but should still get the default (everything shown).
     *
     * @return array{thread_sort:string,show_signatures:bool,show_avatars:bool,show_reactions:bool}
     */
    public function readingDefaults(): array
    {
        return $this->pickReading(PreferenceSchema::resolve([]));
    }

    /**
     * @param array<string,mixed> $r
     * @return array{thread_sort:string,show_signatures:bool,show_avatars:bool,show_reactions:bool}
     */
    private function pickReading(array $r): array
    {
        return [
            'thread_sort' => (string) ($r['thread_sort'] ?? 'last_post'),
            'show_signatures' => (bool) ($r['show_signatures'] ?? true),
            'show_avatars' => (bool) ($r['show_avatars'] ?? true),
            'show_reactions' => (bool) ($r['show_reactions'] ?? true),
        ];
    }

    public function threadsPerPage(int $userId): int
    {
        $v = (int) ($this->prefs->get($userId)['threads_per_page'] ?? 0);
        return in_array($v, PreferenceSchema::THREADS_PER_PAGE, true) ? $v : $this->defaultThreadsPerPage;
    }

    public function postsPerPage(int $userId): int
    {
        $v = (int) ($this->prefs->get($userId)['posts_per_page'] ?? 0);
        return in_array($v, PreferenceSchema::POSTS_PER_PAGE, true) ? $v : $this->defaultPostsPerPage;
    }

    /**
     * Validate + persist one settings section's form. Stamps the schema version
     * and only touches that section's keys.
     *
     * @param array<string,mixed> $input
     */
    public function updateSection(int $userId, string $section, array $input): void
    {
        if (!PreferenceSchema::hasSection($section)) {
            return;
        }
        $changes = PreferenceSchema::validateSection($section, $input);
        $changes['__v'] = PreferenceSchema::VERSION;
        $this->prefs->merge($userId, $changes);
    }

    /**
     * Backwards-compatible reading-section update (the Phase 2 `/settings/
     * preferences` form posts here). Theme/density moved to the appearance form.
     *
     * @param array<string,mixed> $input
     */
    public function update(int $userId, array $input): void
    {
        $this->updateSection($userId, 'reading', $input);
    }

    /**
     * Reset every schema-managed key to its default (removes them from the blob),
     * preserving non-schema keys such as `hide_from_leaderboard`.
     */
    public function reset(int $userId): void
    {
        $removals = ['__v' => null];
        foreach (PreferenceSchema::sections() as $section) {
            foreach (PreferenceSchema::fields($section) as $key => $_spec) {
                $removals[$key] = null;
            }
        }
        $this->prefs->merge($userId, $removals);
    }

    /**
     * A self-describing snapshot of the user's appearance / reading / composing
     * preferences, grouped by section, for the Settings "export" action (P3-01,
     * USER §4). Only schema-managed keys are included (not other subsystems'
     * blob keys such as `hide_from_leaderboard`); a per-page key absent from the
     * blob surfaces as null = the server default.
     *
     * @return array<string, array<string,mixed>>
     */
    public function export(int $userId): array
    {
        $resolved = $this->resolved($userId);
        $sections = [];
        foreach (PreferenceSchema::sections() as $section) {
            $values = [];
            foreach (array_keys(PreferenceSchema::fields($section)) as $key) {
                $values[$key] = $resolved[$key] ?? null;
            }
            $sections[$section] = $values;
        }
        return $sections;
    }
}
