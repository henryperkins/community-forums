<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

/**
 * Foundation F11 - the threat-model dossiers and their negative-fixture index
 * stay in lock-step. Each TM-XX-NN in fixtures.json must appear in its dossier;
 * implemented fixtures must point at an existing test file.
 */
final class ThreatModelIndexTest extends TestCase
{
    private const DIR = 'docs/phase5/threat-models';

    private const MODELS = [
        'supply-chain.md',
        'identity-account-takeover.md',
        'privilege-escalation.md',
        'theme-phishing.md',
        'secret-handling.md',
        'invitation-privilege.md',
    ];

    private const OWNERS = [
        'Foundation', 'Inc1', 'Inc2', 'Inc3', 'Inc4', 'Inc5',
        'Inc6', 'Inc7', 'Inc8', 'Inc9', 'Inc10', 'GateB',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string,mixed> $doc
     * @param array<string,string> $modelContents filename => markdown
     * @return list<string> errors
     */
    private static function validate(array $doc, array $modelContents, string $root): array
    {
        $errors = [];
        $fixtures = $doc['fixtures'] ?? null;
        if (!is_array($fixtures) || $fixtures === []) {
            return ['fixtures missing or empty'];
        }

        $seen = [];
        $perModel = array_fill_keys(self::MODELS, 0);
        foreach ($fixtures as $i => $f) {
            $id = is_array($f) ? (string) ($f['id'] ?? "#$i") : "#$i";
            if (preg_match('/^TM-[A-Z]{2,4}-\d{2}$/', $id) !== 1) {
                $errors[] = "$id: malformed id";
            }
            if (isset($seen[$id])) {
                $errors[] = "$id: duplicate id";
            }
            $seen[$id] = true;

            $model = is_array($f) ? (string) ($f['model'] ?? '') : '';
            if (!in_array($model, self::MODELS, true)) {
                $errors[] = "$id: unknown model '$model'";
            } elseif (!isset($modelContents[$model])) {
                $errors[] = "$id: dossier missing on disk: $model";
            } elseif (!str_contains($modelContents[$model], $id)) {
                $errors[] = "$id: not documented in $model";
            } else {
                $perModel[$model]++;
            }

            if (!is_array($f) || trim((string) ($f['fixture'] ?? '')) === '') {
                $errors[] = "$id: empty fixture description";
            }
            if (!is_array($f) || !in_array($f['owner'] ?? '', self::OWNERS, true)) {
                $errors[] = "$id: unknown owner";
            }
            $status = is_array($f) ? ($f['status'] ?? '') : '';
            if (!in_array($status, ['stub', 'implemented'], true)) {
                $errors[] = "$id: status must be stub|implemented";
            }
            if ($status === 'implemented' && (!is_array($f) || !is_string($f['test'] ?? null) || !is_file($root . '/' . $f['test']))) {
                $errors[] = "$id: implemented fixture must name an existing test file";
            }
        }

        foreach ($perModel as $model => $count) {
            if ($count === 0) {
                $errors[] = "$model: no fixtures indexed";
            }
        }

        return $errors;
    }

    /** @return array{doc:array<string,mixed>,contents:array<string,string>} */
    private static function loadReal(): array
    {
        $dir = self::root() . '/' . self::DIR;
        $doc = json_decode((string) file_get_contents($dir . '/fixtures.json'), true);
        self::assertIsArray($doc, 'fixtures.json must be valid JSON');

        $contents = [];
        foreach (self::MODELS as $model) {
            $path = $dir . '/' . $model;
            self::assertFileExists($path);
            $contents[$model] = (string) file_get_contents($path);
        }

        return ['doc' => $doc, 'contents' => $contents];
    }

    public function test_every_dossier_exists_and_index_is_valid(): void
    {
        ['doc' => $doc, 'contents' => $contents] = self::loadReal();
        $errors = self::validate($doc, $contents, self::root());
        self::assertSame([], $errors, "threat-model index invalid:\n- " . implode("\n- ", $errors));
    }

    public function test_every_dossier_is_recorded_pending_owner_review(): void
    {
        ['contents' => $contents] = self::loadReal();
        foreach ($contents as $model => $markdown) {
            self::assertStringContainsString('pending owner review', $markdown, $model);
        }
    }

    public function test_validator_flags_undocumented_id_bad_owner_and_fake_test_path(): void
    {
        $contents = array_fill_keys(self::MODELS, 'doc mentions TM-SC-01 only');
        $doc = ['version' => 1, 'fixtures' => [
            ['id' => 'TM-SC-01', 'model' => 'supply-chain.md', 'fixture' => 'x', 'owner' => 'Inc2', 'status' => 'stub'],
            ['id' => 'TM-SC-99', 'model' => 'supply-chain.md', 'fixture' => 'x', 'owner' => 'Inc2', 'status' => 'stub'],
            ['id' => 'TM-ID-01', 'model' => 'identity-account-takeover.md', 'fixture' => 'x', 'owner' => 'NotATeam', 'status' => 'stub'],
            ['id' => 'TM-PE-01', 'model' => 'privilege-escalation.md', 'fixture' => 'x', 'owner' => 'Inc6', 'status' => 'implemented', 'test' => 'no/such/Test.php'],
        ]];
        $errors = self::validate($doc, $contents, self::root());
        self::assertContains('TM-SC-99: not documented in supply-chain.md', $errors);
        self::assertContains('TM-ID-01: unknown owner', $errors);
        self::assertContains('TM-PE-01: implemented fixture must name an existing test file', $errors);
        self::assertContains('theme-phishing.md: no fixtures indexed', $errors);
    }
}
