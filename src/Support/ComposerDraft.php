<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\ValidationException;

/** The permitted wrapper fields and canonical destinations of a composer draft. */
final class ComposerDraft
{
    /** @return array{version:int,fields:array<string,string|bool>} */
    public static function state(mixed $state): array
    {
        if (!is_array($state) || ($state['version'] ?? null) !== 1 || !is_array($state['fields'] ?? null)) {
            throw new ValidationException(['draft_state' => 'The draft form state is invalid.']);
        }
        $fields = [];
        foreach (['title', 'board_id', 'is_anonymous', 'to'] as $name) {
            if (!array_key_exists($name, $state['fields'])) { continue; }
            $value = $state['fields'][$name];
            $valid = $name === 'is_anonymous' ? is_bool($value) : is_string($value) && mb_strlen($value) <= 255;
            if ($name === 'board_id') { $valid = is_string($value) && ($value === '' || preg_match('/^[1-9][0-9]{0,18}$/D', $value) === 1); }
            if (!$valid) { throw new ValidationException(['draft_state' => 'The saved ' . str_replace('_', ' ', $name) . ' value is invalid.']); }
            $fields[$name] = $value;
        }
        return ['version' => 1, 'fields' => $fields];
    }

    public static function context(array $draft): ?string
    {
        $context = rawurldecode(str_replace('~', '%', (string) ($draft['context_key'] ?? '')));
        return preg_match('#^(/threads|/messages|/t/[1-9][0-9]*/reply|/messages/[1-9][0-9]*)$#D', $context) === 1 ? $context : null;
    }

    public static function resumeUrl(array $draft): ?string
    {
        $context = self::context($draft);
        if ($context === null) { return null; }
        $query = '?draft=' . (int) $draft['id'];
        if ($context === '/threads') { return '/compose' . $query; }
        if ($context === '/messages') { return '/messages/new' . $query; }
        if (preg_match('#^/t/([0-9]+)/reply$#D', $context, $match) === 1) { return '/t/' . $match[1] . $query . '#reply'; }
        return $context . $query;
    }

    public static function label(array $draft): string
    {
        $context = self::context($draft);
        if ($context === '/threads') { return 'New topic'; }
        if ($context === '/messages') { return 'New direct message'; }
        if ($context !== null && str_starts_with($context, '/t/')) { return 'Thread reply'; }
        return $context !== null ? 'Direct-message reply' : 'Saved draft';
    }

    /** @return array<string,string|bool> */
    public static function values(array $draft): array
    {
        $fields = [];
        $versioned = false;
        $metadata = is_array($draft['metadata'] ?? null) ? $draft['metadata'] : [];
        if (isset($metadata['state'])) {
            try { $fields = self::state($metadata['state'])['fields']; $versioned = true; } catch (ValidationException) { /* Legacy/corrupt metadata cannot become form controls. */ }
        }
        if (!$versioned && is_string($draft['title'] ?? null)) { $fields['title'] = $draft['title']; }
        return $fields + ['body' => (string) ($draft['body'] ?? ''), 'draft_source' => self::source($draft)];
    }

    /** Bind cleanup to the particular saved row and revision that was resumed. */
    public static function source(array $draft): string
    {
        $id = (int) ($draft['id'] ?? 0);
        $revision = (int) ($draft['revision'] ?? 0);
        return $id > 0 && $revision > 0 ? $id . ':' . $revision : '';
    }

    /** Compare only recorded fields; absent checkboxes mean unchecked in an actual POST. */
    public static function matchesSubmission(array $draft, array $input): bool
    {
        $metadata = is_array($draft['metadata'] ?? null) ? $draft['metadata'] : [];
        if (!isset($metadata['state'])) { return true; }
        // Resume starts a new form identity. Its exact source may authorize
        // cleanup of that copy, never a newer revision or a recreated row.
        // Ownership/context and the full body are checked by the repository;
        // all recorded wrapper fields must still match below.
        $source = self::source($draft);
        $resumed = $source !== '' && ($input['draft_source'] ?? null) === $source;
        if (is_string($metadata['submission'] ?? null) && $metadata['submission'] !== ''
            && ($input['idempotency_key'] ?? null) !== $metadata['submission'] && !$resumed) { return false; }
        try { $fields = self::state($metadata['state'])['fields']; } catch (ValidationException) { return false; }
        foreach ($fields as $name => $value) {
            $actual = $name === 'is_anonymous'
                ? in_array($input[$name] ?? false, [true, 1, '1'], true)
                : ($input[$name] ?? null);
            if ($actual !== $value) { return false; }
        }
        return true;
    }
}
