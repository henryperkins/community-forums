<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Support\Markdown;

/**
 * Local structured-output validation (ADR 0019). Takes only the supplied
 * request — never the database — and either returns the fully validated
 * product shape or throws a safe-coded exception. Invalid output is never
 * repaired or partially accepted.
 *
 * Structural violations (shape/keys/types) throw `schema_invalid`; content
 * violations (word limits, citations, candidates, unsafe content) throw
 * `validation_failed`. Content also runs through the existing
 * App\Support\Markdown render/sanitization path before it becomes trusted.
 */
final class ThreadIntelligenceOutputValidator
{
    private const OVERVIEW_WORD_LIMIT = 220;
    private const ITEM_WORD_LIMIT = 40;
    private const TOTAL_WORD_LIMIT = 450;
    private const EXPLANATION_CHAR_LIMIT = 255;
    private const MIN_COMBINED_ITEMS = 3;
    private const MAX_COMBINED_ITEMS = 5;
    private const MAX_RELATED_TOPICS = 3;

    private const UNSAFE_PATTERNS = [
        '/<[a-z!\/]/i',                    // raw HTML tag, comment, or autolink opener
        '/<\?/',                            // raw processing instruction (PHP/XML)
        '/\]\(/',                          // Markdown link/image destination
        '/!\[/',                           // Markdown image opener
        '/```|~~~/',                       // code fences
        '#[a-z][a-z0-9+.\-]*://#i',        // any scheme://host URL
        '/\b(?:javascript|data|vbscript|mailto|file):/i',
        '/(?<![:\w])\/\/(?=\S)/',          // protocol-relative URL
    ];

    private const FORBIDDEN_RENDERED_MARKUP = '~<\s*(?:a|img|pre|script|style|iframe|object|embed|svg|math|form|textarea|button|select|option|link|meta|base|audio|video|source|track|canvas|template|applet|frame|frameset|noscript|picture|map|area)\b|<[^>]+\son[a-z0-9_-]+\s*=~i';

    public function __construct(private readonly Markdown $markdown)
    {
    }

    public function validate(ThreadIntelligenceResult $result, ThreadIntelligenceRequest $request): ValidatedThreadIntelligenceOutput
    {
        if ($result->status !== ThreadIntelligenceResult::STATUS_COMPLETED) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED);
        }

        $output = $result->output;
        $this->assertExactKeys($output, ['overview', 'key_points', 'open_questions', 'related_topics']);

        $overview = $this->structuredItem($output['overview']);
        $keyPoints = $this->structuredItemList($output['key_points']);
        $openQuestions = $this->structuredItemList($output['open_questions']);
        $relatedTopics = $this->structuredRelatedList($output['related_topics']);

        // ---- content rules (validation_failed) ------------------------------

        $eligiblePostIds = array_map(static fn (ThreadIntelligenceEvidencePost $p): int => $p->postId, $request->posts);
        if ($request->baseline !== null) {
            $eligiblePostIds = [...$eligiblePostIds, ...$request->baseline->sourcePostIds];
        }
        if ($request->carryForward !== null) {
            $eligiblePostIds = [...$eligiblePostIds, ...$request->carryForward->sourcePostIds];
        }
        $eligiblePostIds = array_values(array_unique($eligiblePostIds));
        $candidateIds = array_map(static fn (ThreadIntelligenceRelatedCandidate $c): int => $c->threadId, $request->candidates);

        $overviewText = $this->safeText($overview['markdown'], self::OVERVIEW_WORD_LIMIT, allowNewlines: true);
        $overviewSources = $this->citations($overview['source_post_ids'], $eligiblePostIds);

        $combined = count($keyPoints) + count($openQuestions);
        if ($combined < self::MIN_COMBINED_ITEMS || $combined > self::MAX_COMBINED_ITEMS) {
            $this->rejectContent('combined key points/open questions must number three to five');
        }

        $validatedKeyPoints = [];
        foreach ($keyPoints as $item) {
            $validatedKeyPoints[] = [
                'markdown' => $this->safeText($item['markdown'], self::ITEM_WORD_LIMIT, allowNewlines: false),
                'source_post_ids' => $this->citations($item['source_post_ids'], $eligiblePostIds),
            ];
        }
        $validatedOpenQuestions = [];
        foreach ($openQuestions as $item) {
            $validatedOpenQuestions[] = [
                'markdown' => $this->safeText($item['markdown'], self::ITEM_WORD_LIMIT, allowNewlines: false),
                'source_post_ids' => $this->citations($item['source_post_ids'], $eligiblePostIds),
            ];
        }

        if (count($relatedTopics) > self::MAX_RELATED_TOPICS) {
            $this->rejectContent('at most three related topics');
        }
        $validatedRelated = [];
        $seenTargets = [];
        foreach ($relatedTopics as $topic) {
            $threadId = $topic['thread_id'];
            if (isset($seenTargets[$threadId])) {
                $this->rejectContent('related topics must be unique');
            }
            $seenTargets[$threadId] = true;
            if (!in_array($threadId, $candidateIds, true)) {
                $this->rejectContent('related topics must come from the supplied candidates');
            }
            $validatedRelated[] = [
                'thread_id' => $threadId,
                'explanation' => $this->safeExplanation($topic['explanation']),
            ];
        }

        // ---- canonical composition ---------------------------------------------

        $parts = [$overviewText];
        if ($validatedKeyPoints !== []) {
            $parts[] = "### Key points\n\n" . implode("\n", array_map(
                static fn (array $item): string => '- ' . $item['markdown'],
                $validatedKeyPoints,
            ));
        }
        if ($validatedOpenQuestions !== []) {
            $parts[] = "### Open questions\n\n" . implode("\n", array_map(
                static fn (array $item): string => '- ' . $item['markdown'],
                $validatedOpenQuestions,
            ));
        }
        $canonical = implode("\n\n", $parts);

        if ($this->wordCount($canonical) > self::TOTAL_WORD_LIMIT) {
            $this->rejectContent('composed brief exceeds 450 words');
        }
        $this->assertSafeContent($canonical);

        $explanations = array_map(static fn (array $t): string => $t['explanation'], $validatedRelated);
        $moderationText = $canonical;
        if ($explanations !== []) {
            $moderationText .= "\n\n" . implode("\n", $explanations);
        }
        $this->assertSafeContent($moderationText);

        $union = $overviewSources;
        foreach ([...$validatedKeyPoints, ...$validatedOpenQuestions] as $item) {
            $union = [...$union, ...$item['source_post_ids']];
        }
        $union = array_values(array_unique($union));
        sort($union);

        return new ValidatedThreadIntelligenceOutput(
            $canonical,
            $moderationText,
            $overviewText,
            $validatedKeyPoints,
            $validatedOpenQuestions,
            $validatedRelated,
            $union,
            array_map(static fn (array $t): int => $t['thread_id'], $validatedRelated),
        );
    }

    // ---- structural layer (schema_invalid) -----------------------------------

    /** @param list<string> $expected */
    private function assertExactKeys(mixed $node, array $expected): void
    {
        if (!is_array($node)) {
            $this->rejectSchema('output must be a JSON object');
        }
        $keys = array_keys($node);
        sort($keys);
        $sorted = $expected;
        sort($sorted);
        if ($keys !== $sorted) {
            $this->rejectSchema('output keys must match the schema exactly');
        }
    }

    /** @return array{markdown:string, source_post_ids:list<int>} */
    private function structuredItem(mixed $node): array
    {
        $this->assertExactKeys($node, ['markdown', 'source_post_ids']);
        if (!is_string($node['markdown'])) {
            $this->rejectSchema('markdown must be a string');
        }
        if (!is_array($node['source_post_ids']) || !array_is_list($node['source_post_ids'])) {
            $this->rejectSchema('source_post_ids must be a list');
        }
        foreach ($node['source_post_ids'] as $id) {
            if (!is_int($id)) {
                $this->rejectSchema('source_post_ids must be integers');
            }
        }
        return ['markdown' => $node['markdown'], 'source_post_ids' => array_values($node['source_post_ids'])];
    }

    /** @return list<array{markdown:string, source_post_ids:list<int>}> */
    private function structuredItemList(mixed $node): array
    {
        if (!is_array($node) || !array_is_list($node)) {
            $this->rejectSchema('items must be a list');
        }
        return array_map(fn (mixed $item): array => $this->structuredItem($item), $node);
    }

    /** @return list<array{thread_id:int, explanation:string}> */
    private function structuredRelatedList(mixed $node): array
    {
        if (!is_array($node) || !array_is_list($node)) {
            $this->rejectSchema('related_topics must be a list');
        }
        $topics = [];
        foreach ($node as $item) {
            $this->assertExactKeys($item, ['thread_id', 'explanation']);
            if (!is_int($item['thread_id'])) {
                $this->rejectSchema('thread_id must be an integer');
            }
            if (!is_string($item['explanation'])) {
                $this->rejectSchema('explanation must be a string');
            }
            $topics[] = ['thread_id' => $item['thread_id'], 'explanation' => $item['explanation']];
        }
        return $topics;
    }

    // ---- content layer (validation_failed) --------------------------------------

    private function safeText(string $markdown, int $wordLimit, bool $allowNewlines): string
    {
        $text = trim($markdown);
        if ($text === '') {
            $this->rejectContent('text must not be empty');
        }
        if (!$allowNewlines && preg_match('/\R/', $text) === 1) {
            $this->rejectContent('list items must be single-line');
        }
        if ($this->wordCount($text) > $wordLimit) {
            $this->rejectContent('word limit exceeded');
        }
        $this->assertSafeContent($text);
        return $text;
    }

    private function safeExplanation(string $explanation): string
    {
        $text = trim($explanation);
        if ($text === '') {
            $this->rejectContent('explanations must not be empty');
        }
        if (strlen($text) > self::EXPLANATION_CHAR_LIMIT) {
            $this->rejectContent('explanations are bounded to 255 characters');
        }
        if (preg_match('/\R/', $text) === 1) {
            $this->rejectContent('explanations must be single-line');
        }
        if (preg_match('/[.!?][)"\'\]]*\s+\S/u', $text) === 1) {
            $this->rejectContent('explanations must be one sentence');
        }
        $this->assertSafeContent($text);
        return $text;
    }

    /** @param list<int> $ids @param list<int> $eligible @return list<int> */
    private function citations(array $ids, array $eligible): array
    {
        if ($ids === []) {
            $this->rejectContent('every item needs at least one cited source');
        }
        if (count($ids) !== count(array_unique($ids))) {
            $this->rejectContent('item citations must be unique within the item');
        }
        foreach ($ids as $id) {
            if (!in_array($id, $eligible, true)) {
                $this->rejectContent('citations must reference supplied eligible posts');
            }
        }
        return $ids;
    }

    private function assertSafeContent(string $text): void
    {
        foreach (self::UNSAFE_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $this->rejectContent('raw HTML, links, images, code fences, and URLs are not allowed');
            }
        }

        try {
            $rendered = $this->markdown->render($text);
        } catch (\Throwable) {
            $this->rejectContent('content could not be rendered safely');
        }
        if (preg_match(self::FORBIDDEN_RENDERED_MARKUP, $rendered) !== 0) {
            $this->rejectContent('rendered content must not contain interactive or executable markup');
        }
    }

    private function wordCount(string $text): int
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        return $tokens === false ? PHP_INT_MAX : count($tokens);
    }

    /** $reason documents the call site only; the exception carries the safe code, nothing else. */
    private function rejectSchema(string $reason): never
    {
        throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
    }

    /** $reason documents the call site only; the exception carries the safe code, nothing else. */
    private function rejectContent(string $reason): never
    {
        throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::VALIDATION_FAILED);
    }
}
