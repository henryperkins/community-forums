<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Domain\User;
use App\Repository\AttachmentRepository;
use App\Repository\BrandingRevisionRepository;
use App\Repository\SettingRepository;
use App\Support\BrandingDocument;

/**
 * Versioned branding (ADR 0054, increment 2). Every published change to the
 * branding document — a branding save, a community-name change in Settings, a
 * restore or a reset — commits through here: one transaction locks the
 * `brand_revision` pointer, refuses a stale editing session unless it
 * explicitly confirmed overwriting the newer revision, applies the change,
 * and records the whole resulting document with the keys it changed. The
 * first recorded change also records the configuration it replaced, so an
 * existing installation can always return to where it started.
 */
final class BrandingRevisionService
{
    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private BrandingRevisionRepository $revisions,
        private ?AttachmentRepository $attachments,
        private string $defaultName,
    ) {
    }

    public function currentRevision(): int
    {
        $value = $this->settings->get('brand_revision', 0);
        return is_int($value) && $value > 0 ? $value : 0;
    }

    /** @return array<string,mixed> */
    public function published(): array
    {
        return BrandingDocument::read($this->settings, $this->defaultName);
    }

    /**
     * @param callable():mixed $apply writes the change through SettingRepository
     * @param ?int $baseRevision the revision the editor started from; null skips
     *   the stale-session check (explicit resets and single-field writers)
     * @param ?int $overwrite the newer revision the editor confirmed replacing
     * @param ?callable(int,list<string>):void $recorded runs inside the same
     *   transaction once a revision is recorded (its audit entry), so the
     *   configuration, its revision and its audit record commit together
     * @return array{revision:int,changed:list<string>,result:mixed,before:array<string,mixed>,after:array<string,mixed>}
     *
     * @throws BrandingConflictException when $baseRevision is stale and not confirmed
     */
    public function commit(
        ?User $actor,
        string $action,
        callable $apply,
        ?int $baseRevision = null,
        ?int $overwrite = null,
        ?int $restoredFrom = null,
        ?callable $recorded = null,
    ): array {
        return $this->db->transaction(function () use ($actor, $action, $apply, $baseRevision, $overwrite, $restoredFrom, $recorded): array {
            $locked = $this->settings->lockForUpdate('brand_revision', 0);
            $current = is_int($locked) && $locked > 0 ? $locked : 0;
            if ($baseRevision !== null && $baseRevision !== $current && $overwrite !== $current) {
                throw new BrandingConflictException($current, $this->revisions->after(max(0, $baseRevision)));
            }

            $before = $this->published();
            $result = $apply();
            $after = $this->published();
            $changed = BrandingDocument::changedKeys($before, $after);
            if ($changed === []) {
                return ['revision' => $current, 'changed' => [], 'result' => $result, 'before' => $before, 'after' => $after];
            }

            if ($current === 0) {
                // The configuration this first recorded change replaces.
                $this->revisions->create($before, [], 'baseline', null);
            }
            $revision = $this->revisions->create($after, $changed, $action, $actor?->id(), $restoredFrom);
            $this->settings->set('brand_revision', $revision);
            $this->settings->set('brand_version', bin2hex(random_bytes(8)));
            if ($recorded !== null) {
                $recorded($revision, $changed);
            }

            return ['revision' => $revision, 'changed' => $changed, 'result' => $result, 'before' => $before, 'after' => $after];
        });
    }

    /**
     * The document a revision restores, with any logo or favicon whose media no
     * longer exists cleared (and named), so a restore never points the public
     * chrome at a missing file.
     *
     * @return array{document:array<string,mixed>,missing:list<string>}|null
     */
    public function restorable(int $revisionId): ?array
    {
        $revision = $this->revisions->find($revisionId);
        if ($revision === null) {
            return null;
        }
        $document = BrandingDocument::normalize($revision['document'], $this->defaultName);
        $missing = [];
        foreach (BrandingDocument::mediaIds($document) as $key => $id) {
            $asset = $this->attachments?->find($id);
            if ($asset === null || (string) $asset['status'] === 'deleted') {
                $document[$key] = '';
                $missing[] = $key;
            }
        }
        return ['document' => $document, 'missing' => $missing];
    }

    /**
     * Brand media referenced by any revision, so retention and restore can
     * prove that every restorable logo still exists.
     *
     * @return list<int>
     */
    public function retainedMediaIds(): array
    {
        $ids = [];
        $offset = 0;
        do {
            $page = $this->revisions->page(100, $offset);
            foreach ($page as $revision) {
                foreach (BrandingDocument::mediaIds($revision['document']) as $id) {
                    $ids[$id] = true;
                }
            }
            $offset += 100;
        } while (count($page) === 100);
        return array_map('intval', array_keys($ids));
    }
}
