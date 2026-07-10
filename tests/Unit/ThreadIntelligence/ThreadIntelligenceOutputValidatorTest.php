<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePost;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligenceRelatedCandidate;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use PHPUnit\Framework\TestCase;

/**
 * Pins every structured-output rule from the approved design (plan Task 2):
 * exact schema shape, word limits, citation eligibility, candidate bounds,
 * explanation bounds, executable-content rejection, canonical composition,
 * and the moderation-text contract. The validator trusts only the supplied
 * request — never the database — and never repairs invalid output.
 */
final class ThreadIntelligenceOutputValidatorTest extends TestCase
{
    private ThreadIntelligenceOutputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ThreadIntelligenceOutputValidator();
    }

    /** @param list<int> $postIds @param list<int> $candidateIds */
    private function request(array $postIds = [11, 12, 13], array $candidateIds = [201, 202, 203, 204]): ThreadIntelligenceRequest
    {
        $posts = [];
        $speaker = 0;
        foreach ($postIds as $id) {
            $speaker++;
            $posts[] = new ThreadIntelligenceEvidencePost($id, '2026-07-10T10:0' . ($speaker % 10) . ':00Z', 'speaker-' . $speaker, 'Public body of post ' . $id . '.');
        }
        $candidates = [];
        $rank = 0;
        foreach ($candidateIds as $id) {
            $rank++;
            $candidates[] = new ThreadIntelligenceRelatedCandidate($id, 'Candidate ' . $id, 'Excerpt for ' . $id, ['tag-a'], 1, 0.5, $rank, '2026-07-09T09:00:00Z');
        }

        return new ThreadIntelligenceRequest(
            threadId: 7,
            threadTitle: 'Widget upgrade breaks login',
            baseline: null,
            carryForward: null,
            posts: $posts,
            candidates: $candidates,
            sourceSnapshotHash: str_repeat('ab', 32),
            promptVersion: 'thread-intelligence-v1',
            windowNumber: 0,
            windowCount: 1,
        );
    }

    /** @return array<string,mixed> a fully valid structured output against request([11,12,13],[201..204]) */
    private function validOutput(): array
    {
        return [
            'overview' => [
                'markdown' => 'The discussion covers a login failure after the widget upgrade and converges on a workaround.',
                'source_post_ids' => [11, 12],
            ],
            'key_points' => [
                ['markdown' => 'The upgrade changed the session cookie name.', 'source_post_ids' => [11]],
                ['markdown' => 'Clearing site data restores login for most members.', 'source_post_ids' => [12, 13]],
            ],
            'open_questions' => [
                ['markdown' => 'Whether single sign-on installs need a separate fix.', 'source_post_ids' => [13]],
            ],
            'related_topics' => [
                ['thread_id' => 201, 'explanation' => 'Covers the same cookie rename for the previous release'],
                ['thread_id' => 203, 'explanation' => 'Documents the single sign-on configuration this thread questions'],
            ],
        ];
    }

    /** @param array<string,mixed> $output */
    private function providerResult(array $output, string $status = 'completed', ?string $reason = null): ThreadIntelligenceResult
    {
        return new ThreadIntelligenceResult($output, 'resp_test123', $status, $reason, new ThreadIntelligenceUsage(100, 50, 10, 0));
    }

    /** @param array<string,mixed> $output */
    private function assertRejected(array $output, string $expectedCode, string $context = ''): void
    {
        try {
            $this->validator->validate($this->providerResult($output), $this->request());
            self::fail('expected rejection (' . $expectedCode . ') ' . $context);
        } catch (ThreadIntelligenceProviderException $e) {
            self::assertSame($expectedCode, $e->safeCode(), $context);
        }
    }

    // ---- happy path ---------------------------------------------------------

    public function test_valid_output_produces_canonical_markdown_with_stable_headings_and_order(): void
    {
        $validated = $this->validator->validate($this->providerResult($this->validOutput()), $this->request());

        $expected = "The discussion covers a login failure after the widget upgrade and converges on a workaround.\n\n"
            . "### Key points\n\n"
            . "- The upgrade changed the session cookie name.\n"
            . "- Clearing site data restores login for most members.\n\n"
            . "### Open questions\n\n"
            . '- Whether single sign-on installs need a separate fix.';
        self::assertSame($expected, $validated->canonicalMarkdown());

        self::assertSame([11, 12, 13], $validated->sourcePostIds(), 'citation union, ascending and unique');
        self::assertSame([201, 203], $validated->relatedThreadIds());
        self::assertSame('The discussion covers a login failure after the widget upgrade and converges on a workaround.', $validated->overview());
        self::assertCount(2, $validated->keyPoints());
        self::assertSame([11], $validated->keyPoints()[0]['source_post_ids']);
        self::assertCount(1, $validated->openQuestions());
        self::assertCount(2, $validated->relatedTopics());
        self::assertSame(201, $validated->relatedTopics()[0]['thread_id']);
    }

    public function test_moderation_text_contains_the_complete_brief_plus_every_related_explanation(): void
    {
        $validated = $this->validator->validate($this->providerResult($this->validOutput()), $this->request());

        $text = $validated->moderationText();
        self::assertStringContainsString($validated->canonicalMarkdown(), $text);
        self::assertStringContainsString('Covers the same cookie rename for the previous release', $text);
        self::assertStringContainsString('Documents the single sign-on configuration this thread questions', $text);
    }

    public function test_sections_without_items_are_omitted_from_the_canonical_markdown(): void
    {
        $output = $this->validOutput();
        $output['key_points'] = [
            ['markdown' => 'First point.', 'source_post_ids' => [11]],
            ['markdown' => 'Second point.', 'source_post_ids' => [12]],
            ['markdown' => 'Third point.', 'source_post_ids' => [13]],
        ];
        $output['open_questions'] = [];

        $validated = $this->validator->validate($this->providerResult($output), $this->request());
        self::assertStringContainsString('### Key points', $validated->canonicalMarkdown());
        self::assertStringNotContainsString('### Open questions', $validated->canonicalMarkdown());
    }

    public function test_zero_related_topics_is_valid(): void
    {
        $output = $this->validOutput();
        $output['related_topics'] = [];
        $validated = $this->validator->validate($this->providerResult($output), $this->request());
        self::assertSame([], $validated->relatedThreadIds());
    }

    // ---- completion status ---------------------------------------------------

    public function test_incomplete_result_is_classified_as_output_truncated(): void
    {
        try {
            $this->validator->validate($this->providerResult($this->validOutput(), 'incomplete', 'max_output_tokens'), $this->request());
            self::fail('expected output_truncated');
        } catch (ThreadIntelligenceProviderException $e) {
            self::assertSame(ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED, $e->safeCode());
        }
    }

    // ---- structural schema rules ----------------------------------------------

    public function test_every_required_top_level_key_is_mandatory(): void
    {
        foreach (['overview', 'key_points', 'open_questions', 'related_topics'] as $key) {
            $output = $this->validOutput();
            unset($output[$key]);
            $this->assertRejected($output, ThreadIntelligenceFailureCode::SCHEMA_INVALID, "missing $key");
        }
    }

    public function test_additional_keys_are_rejected_at_every_level(): void
    {
        $extraTop = $this->validOutput();
        $extraTop['confidence'] = 0.9;
        $this->assertRejected($extraTop, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'extra top-level key');

        $extraOverview = $this->validOutput();
        $extraOverview['overview']['author'] = 'model';
        $this->assertRejected($extraOverview, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'extra overview key');

        $extraItem = $this->validOutput();
        $extraItem['key_points'][0]['weight'] = 1;
        $this->assertRejected($extraItem, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'extra item key');

        $extraRelated = $this->validOutput();
        $extraRelated['related_topics'][0]['url'] = 'https://evil.example';
        $this->assertRejected($extraRelated, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'extra related key');
    }

    public function test_wrong_value_types_are_rejected(): void
    {
        $intMarkdown = $this->validOutput();
        $intMarkdown['overview']['markdown'] = 42;
        $this->assertRejected($intMarkdown, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'non-string markdown');

        $stringIds = $this->validOutput();
        $stringIds['overview']['source_post_ids'] = ['11'];
        $this->assertRejected($stringIds, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'string source ids');

        $stringThreadId = $this->validOutput();
        $stringThreadId['related_topics'][0]['thread_id'] = '201';
        $this->assertRejected($stringThreadId, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'string thread id');

        $notAList = $this->validOutput();
        $notAList['key_points'] = ['markdown' => 'x', 'source_post_ids' => [11]];
        $this->assertRejected($notAList, ThreadIntelligenceFailureCode::SCHEMA_INVALID, 'items must be a list');
    }

    // ---- word limits ------------------------------------------------------------

    public function test_overview_over_220_words_is_rejected(): void
    {
        $output = $this->validOutput();
        $output['overview']['markdown'] = implode(' ', array_fill(0, 221, 'word'));
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, '221-word overview');

        $ok = $this->validOutput();
        $ok['overview']['markdown'] = implode(' ', array_fill(0, 220, 'word'));
        self::assertNotNull($this->validator->validate($this->providerResult($ok), $this->request()));
    }

    public function test_item_over_40_words_is_rejected(): void
    {
        $output = $this->validOutput();
        $output['key_points'][0]['markdown'] = implode(' ', array_fill(0, 41, 'word'));
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, '41-word key point');

        $ok = $this->validOutput();
        $ok['open_questions'][0]['markdown'] = implode(' ', array_fill(0, 40, 'word'));
        self::assertNotNull($this->validator->validate($this->providerResult($ok), $this->request()));
    }

    public function test_combined_items_must_be_three_to_five(): void
    {
        $two = $this->validOutput();
        $two['open_questions'] = [];
        $this->assertRejected($two, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'two combined items');

        $six = $this->validOutput();
        $six['key_points'][] = ['markdown' => 'Fourth.', 'source_post_ids' => [11]];
        $six['key_points'][] = ['markdown' => 'Fifth.', 'source_post_ids' => [12]];
        $six['open_questions'][] = ['markdown' => 'Sixth?', 'source_post_ids' => [13]];
        $this->assertRejected($six, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'six combined items');

        $five = $this->validOutput();
        $five['key_points'][] = ['markdown' => 'Fourth.', 'source_post_ids' => [11]];
        $five['open_questions'][] = ['markdown' => 'Fifth?', 'source_post_ids' => [12]];
        self::assertNotNull($this->validator->validate($this->providerResult($five), $this->request()));
    }

    // ---- citation rules ------------------------------------------------------------

    public function test_every_item_needs_at_least_one_source(): void
    {
        $output = $this->validOutput();
        $output['key_points'][0]['source_post_ids'] = [];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'empty item sources');

        $output = $this->validOutput();
        $output['overview']['source_post_ids'] = [];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'empty overview sources');
    }

    public function test_duplicate_source_ids_within_one_item_are_rejected(): void
    {
        $output = $this->validOutput();
        $output['overview']['source_post_ids'] = [11, 11];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'duplicate overview ids');
    }

    public function test_hallucinated_source_ids_outside_the_supplied_evidence_are_rejected(): void
    {
        $output = $this->validOutput();
        $output['key_points'][0]['source_post_ids'] = [999];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'unknown post id');
    }

    public function test_the_same_source_may_be_cited_by_different_items(): void
    {
        $output = $this->validOutput();
        $output['key_points'][0]['source_post_ids'] = [11];
        $output['key_points'][1]['source_post_ids'] = [11];
        $validated = $this->validator->validate($this->providerResult($output), $this->request());
        self::assertContains(11, $validated->sourcePostIds());
    }

    // ---- related-topic rules -----------------------------------------------------------

    public function test_more_than_three_related_topics_are_rejected(): void
    {
        $output = $this->validOutput();
        $output['related_topics'] = [
            ['thread_id' => 201, 'explanation' => 'One'],
            ['thread_id' => 202, 'explanation' => 'Two'],
            ['thread_id' => 203, 'explanation' => 'Three'],
            ['thread_id' => 204, 'explanation' => 'Four'],
        ];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'four related topics');
    }

    public function test_duplicate_related_targets_are_rejected(): void
    {
        $output = $this->validOutput();
        $output['related_topics'] = [
            ['thread_id' => 201, 'explanation' => 'One'],
            ['thread_id' => 201, 'explanation' => 'Again'],
        ];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'duplicate related target');
    }

    public function test_related_ids_outside_the_candidate_set_are_rejected(): void
    {
        $output = $this->validOutput();
        $output['related_topics'] = [['thread_id' => 999, 'explanation' => 'Invented']];
        $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'invented candidate');
    }

    public function test_multi_sentence_or_overlong_explanations_are_rejected(): void
    {
        $multi = $this->validOutput();
        $multi['related_topics'] = [['thread_id' => 201, 'explanation' => 'First sentence. Second sentence here']];
        $this->assertRejected($multi, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'two sentences');

        $long = $this->validOutput();
        $long['related_topics'] = [['thread_id' => 201, 'explanation' => str_repeat('a', 256)]];
        $this->assertRejected($long, ThreadIntelligenceFailureCode::VALIDATION_FAILED, '256-char explanation');

        $newline = $this->validOutput();
        $newline['related_topics'] = [['thread_id' => 201, 'explanation' => "line one\nline two"]];
        $this->assertRejected($newline, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'newline in explanation');

        $trailingStop = $this->validOutput();
        $trailingStop['related_topics'] = [['thread_id' => 201, 'explanation' => 'A single sentence with a trailing period.']];
        self::assertNotNull($this->validator->validate($this->providerResult($trailingStop), $this->request()), 'one sentence ending in a period is fine');
    }

    // ---- executable / unsafe content -----------------------------------------------------

    public function test_raw_html_markdown_links_images_fences_and_url_schemes_are_rejected_everywhere(): void
    {
        $cases = [
            'raw html tag' => '<script>alert(1)</script> in the overview',
            'html comment' => 'text <!-- sneaky --> more',
            'markdown link' => 'see [docs](https://example.com) for detail',
            'markdown image' => 'look ![alt](https://example.com/x.png)',
            'code fence' => "```php\necho 1;\n```",
            'https url' => 'visit https://example.com now',
            'javascript scheme' => 'click javascript:alert(1)',
            'data scheme' => 'open data:text/html;base64,AAAA',
            'autolink' => 'go to <https://example.com>',
            'protocol relative' => 'load //evil.example/x.js',
        ];
        foreach ($cases as $label => $payload) {
            $inOverview = $this->validOutput();
            $inOverview['overview']['markdown'] = $payload;
            $this->assertRejected($inOverview, ThreadIntelligenceFailureCode::VALIDATION_FAILED, "overview: $label");

            $inItem = $this->validOutput();
            $inItem['key_points'][0]['markdown'] = $payload;
            $this->assertRejected($inItem, ThreadIntelligenceFailureCode::VALIDATION_FAILED, "item: $label");

            $inExplanation = $this->validOutput();
            $inExplanation['related_topics'] = [['thread_id' => 201, 'explanation' => str_replace("\n", ' ', $payload)]];
            $this->assertRejected($inExplanation, ThreadIntelligenceFailureCode::VALIDATION_FAILED, "explanation: $label");
        }
    }

    public function test_empty_or_whitespace_overview_is_rejected(): void
    {
        foreach (['', '   ', "\n\t"] as $empty) {
            $output = $this->validOutput();
            $output['overview']['markdown'] = $empty;
            $this->assertRejected($output, ThreadIntelligenceFailureCode::VALIDATION_FAILED, 'empty overview');
        }
    }

    public function test_safe_inline_markdown_emphasis_is_allowed(): void
    {
        $output = $this->validOutput();
        $output['overview']['markdown'] = 'The **cookie rename** is the *root cause* according to `session.name` reports.';
        self::assertNotNull($this->validator->validate($this->providerResult($output), $this->request()));
    }
}
