<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class ThreadIntelligenceEvidenceMapTest extends TestCase
{
    private const GATES = [
        'live_eval', 'human_rubric', 'browser_desktop', 'browser_mobile',
        'no_js', 'a11y', 'security_privacy', 'worker_concurrency',
        'migration_upgrade', 'backup_restore', 'runtime_rollback', 'runbook',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_every_thread_intelligence_graduation_gate_is_complete(): void
    {
        $index = (string) file_get_contents(self::root() . '/docs/evidence/phase4-closeout/thread-intelligence-index.md');
        foreach (self::GATES as $gate) {
            self::assertStringContainsString('- [x] ' . $gate . ':', $index, 'missing gate: ' . $gate);
        }
        self::assertDoesNotMatchRegularExpression(
            '/sk-(?:proj-)?[a-z0-9_-]{20,}|"(?:raw_)?prompt"\s*:|"raw_response"\s*:|"post_body"\s*:|"generated_text"\s*:/i',
            $index,
        );
        // Match the marker line itself, not the footer prose that names it.
        if (preg_match('/^default_on: complete\b/m', $index) === 1) {
            self::assertStringContainsString('- [x] post_flip_double_suite:', $index);
        }
    }

    public function test_checked_gates_link_existing_repository_evidence_and_all_thread_intelligence_evidence_is_redacted(): void
    {
        $root = self::root();
        $index = (string) file_get_contents($root . '/docs/evidence/phase4-closeout/thread-intelligence-index.md');
        foreach (self::GATES as $gate) {
            self::assertMatchesRegularExpression(
                '/^- \[x\] ' . preg_quote($gate, '/') . ':.*`(docs\/[^`]+)`/m',
                $index,
                'checked gate must link repository evidence: ' . $gate,
            );
            preg_match('/^- \[x\] ' . preg_quote($gate, '/') . ':.*`(docs\/[^`]+)`/m', $index, $match);
            self::assertFileExists($root . '/' . $match[1], 'missing linked evidence for ' . $gate);
        }

        $paths = glob($root . '/docs/evidence/phase4-closeout/thread-intelligence-*') ?: [];
        self::assertNotEmpty($paths);
        $sensitive = '/sk-(?:proj-)?[a-z0-9_-]{20,}|"(?:raw_)?prompt"\s*:|"raw_response"\s*:|"post_body"\s*:|"generated_text"\s*:/i';
        foreach ($paths as $path) {
            self::assertDoesNotMatchRegularExpression($sensitive, (string) file_get_contents($path), basename($path));
        }
    }
}
