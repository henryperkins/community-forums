<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Foundation F10 - machine-checkable R0-R5 requirement ledger. Gate-pass
 * evaluability: every Gate A DoD item exists in the ledger; any state >= R3
 * must link evidence that exists on this commit; every Phase 5 flag has a
 * documented rollback path.
 */
final class Phase5EvidenceMapTest extends TestCase
{
    private const STATES = ['R0', 'R1', 'R2', 'R3', 'R4', 'R5'];

    /** Gate A checklist, in order (verified 23 items). */
    private const GATE_A_DOD_IDS = [
        'GA-DOD-01', 'GA-DOD-02', 'GA-DOD-03', 'GA-DOD-04', 'GA-DOD-05',
        'GA-DOD-06', 'GA-DOD-07', 'GA-DOD-08', 'GA-DOD-09', 'GA-DOD-10',
        'GA-DOD-11', 'GA-DOD-12', 'GA-DOD-13', 'GA-DOD-14', 'GA-DOD-15',
        'GA-DOD-16', 'GA-DOD-17', 'GA-DOD-18', 'GA-DOD-19', 'GA-DOD-20',
        'GA-DOD-21', 'GA-DOD-22', 'GA-DOD-23',
    ];

    /** Must stay in lock-step with FeatureFlags::DEFAULTS' Phase 5 block. */
    private const PHASE5_FLAGS = [
        'package_registry', 'package_themes', 'capabilities', 'passkeys',
        'provider_registry', 'invitations', 'service_secrets', 'api_tokens',
        'webhooks', 'first_party_hooks',
        'server_extensions', 'governance', 'service_principals', 'verified_links',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /** @return array<string,mixed> */
    private static function loadLedger(): array
    {
        $path = self::root() . '/docs/phase5/requirement-ledger.json';
        self::assertFileExists($path);
        $doc = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($doc, 'ledger must be valid JSON');

        return $doc;
    }

    /**
     * @param array<string,mixed> $doc
     * @return list<string> human-readable errors (empty = valid)
     */
    private static function validate(array $doc, string $root): array
    {
        $errors = [];
        $requirements = $doc['requirements'] ?? null;
        if (!is_array($requirements) || $requirements === []) {
            return ['requirements missing or empty'];
        }

        $seen = [];
        foreach ($requirements as $i => $req) {
            $id = is_array($req) ? ($req['id'] ?? "#$i") : "#$i";
            foreach (['id', 'gate', 'workstream', 'title', 'state'] as $field) {
                if (!is_array($req) || !is_string($req[$field] ?? null) || trim((string) ($req[$field] ?? '')) === '') {
                    $errors[] = "$id: missing/empty $field";
                }
            }
            if (isset($seen[$id])) {
                $errors[] = "$id: duplicate id";
            }
            $seen[$id] = true;

            $state = is_array($req) ? (string) ($req['state'] ?? '') : '';
            if (!in_array($state, self::STATES, true)) {
                $errors[] = "$id: unknown state '$state'";
            }
            if (!is_array($req) || !in_array($req['gate'] ?? '', ['A', 'B'], true)) {
                $errors[] = "$id: gate must be A or B";
            }

            $evidence = is_array($req) ? ($req['evidence'] ?? []) : [];
            if (!is_array($evidence)) {
                $errors[] = "$id: evidence must be an array";
                $evidence = [];
            }
            foreach ($evidence as $path) {
                if (!is_string($path) || !is_file($root . '/' . $path)) {
                    $errors[] = "$id: evidence path does not exist: " . (is_string($path) ? $path : gettype($path));
                }
            }
            if (in_array($state, ['R3', 'R4', 'R5'], true) && $evidence === []) {
                $errors[] = "$id: state $state requires at least one evidence link";
            }
        }

        foreach (self::GATE_A_DOD_IDS as $dodId) {
            if (!isset($seen[$dodId])) {
                $errors[] = "missing Gate A DoD item $dodId";
            }
        }
        foreach (array_keys($seen) as $id) {
            if (str_starts_with((string) $id, 'GA-DOD-') && !in_array($id, self::GATE_A_DOD_IDS, true)) {
                $errors[] = "$id: not a known Gate A item";
            }
        }

        $flags = $doc['flags'] ?? null;
        if (!is_array($flags)) {
            $errors[] = 'flags map missing';
        } else {
            foreach (self::PHASE5_FLAGS as $flag) {
                if (!is_string($flags[$flag]['rollback'] ?? null) || trim((string) ($flags[$flag]['rollback'] ?? '')) === '') {
                    $errors[] = "flag $flag: missing rollback path";
                }
            }
            foreach (array_keys($flags) as $flag) {
                if (!in_array($flag, self::PHASE5_FLAGS, true)) {
                    $errors[] = "flag $flag: not a declared Phase 5 flag";
                }
            }
        }

        return $errors;
    }

    public function test_ledger_is_valid_and_every_claim_is_evidenced(): void
    {
        $errors = self::validate(self::loadLedger(), self::root());
        self::assertSame([], $errors, "requirement-ledger.json invalid:\n- " . implode("\n- ", $errors));
    }

    public function test_validator_flags_overclaimed_state_without_evidence(): void
    {
        $doc = self::minimalValidDoc();
        $doc['requirements'][0]['state'] = 'R3';
        $doc['requirements'][0]['evidence'] = [];
        self::assertContains('GA-DOD-01: state R3 requires at least one evidence link', self::validate($doc, self::root()));
    }

    public function test_validator_flags_missing_dod_item_unknown_state_and_dead_evidence(): void
    {
        $doc = self::minimalValidDoc();
        unset($doc['requirements'][22]);
        $doc['requirements'] = array_values($doc['requirements']);
        $doc['requirements'][0]['state'] = 'R9';
        $doc['requirements'][1]['evidence'] = ['no/such/file.md'];
        $errors = self::validate($doc, self::root());
        self::assertContains('missing Gate A DoD item GA-DOD-23', $errors);
        self::assertContains("GA-DOD-01: unknown state 'R9'", $errors);
        self::assertContains('GA-DOD-02: evidence path does not exist: no/such/file.md', $errors);
    }

    public function test_validator_flags_missing_flag_rollback(): void
    {
        $doc = self::minimalValidDoc();
        unset($doc['flags']['passkeys']);
        self::assertContains('flag passkeys: missing rollback path', self::validate($doc, self::root()));
    }

    /** @return array<string,mixed> a synthetic doc that passes validation */
    private static function minimalValidDoc(): array
    {
        $requirements = [];
        foreach (self::GATE_A_DOD_IDS as $id) {
            $requirements[] = ['id' => $id, 'gate' => 'A', 'workstream' => 'P5-16', 'title' => 't', 'state' => 'R1', 'evidence' => []];
        }
        $flags = [];
        foreach (self::PHASE5_FLAGS as $flag) {
            $flags[$flag] = ['gate' => 'A', 'rollback' => 'features override to dark'];
        }

        return ['version' => 1, 'updated' => '2026-07-01', 'requirements' => $requirements, 'flags' => $flags];
    }
}
