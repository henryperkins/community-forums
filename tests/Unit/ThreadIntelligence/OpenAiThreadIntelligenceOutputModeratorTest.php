<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\ArrayOpenAiTransport;
use App\Service\ThreadIntelligence\OpenAiThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\OpenAiTransportResponse;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use PHPUnit\Framework\TestCase;

/**
 * Pins the Moderations API integration (plan Task 3): exact endpoint/model/
 * input, bounded flagged-category extraction, the fixed 15-second timeout,
 * and fail-closed moderation_transport classification. Network-free.
 */
final class OpenAiThreadIntelligenceOutputModeratorTest extends TestCase
{
    private ArrayOpenAiTransport $transport;
    private OpenAiThreadIntelligenceOutputModerator $moderator;

    protected function setUp(): void
    {
        $this->transport = new ArrayOpenAiTransport();
        $this->moderator = new OpenAiThreadIntelligenceOutputModerator($this->transport);
    }

    /** @param array<string,bool> $categories */
    private function moderationJson(bool $flagged, array $categories = []): array
    {
        return [
            'id' => 'modr-1',
            'model' => 'omni-moderation-latest',
            'results' => [
                ['flagged' => $flagged, 'categories' => $categories, 'category_scores' => []],
            ],
        ];
    }

    public function test_moderation_sends_exactly_the_model_and_full_text_to_v1_moderations(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->moderationJson(false)));
        $text = "Brief markdown\n\n### Key points\n\n- One\n\nRelated explanation one\nRelated explanation two";
        $this->moderator->moderate($text);

        $sent = $this->transport->requests();
        self::assertCount(1, $sent);
        self::assertSame('/v1/moderations', $sent[0]['path']);
        self::assertSame(15, $sent[0]['timeout'], 'moderation uses the fixed 15-second timeout');
        self::assertSame(['model' => 'omni-moderation-latest', 'input' => $text], $sent[0]['payload']);
    }

    public function test_clean_output_returns_unflagged_with_no_categories(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->moderationJson(false, ['harassment' => false])));
        $verdict = $this->moderator->moderate('calm text');

        self::assertFalse($verdict->flagged);
        self::assertSame([], $verdict->flaggedCategories);
    }

    public function test_flagged_output_returns_only_the_bounded_true_category_names(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->moderationJson(true, [
            'harassment' => true,
            'violence' => false,
            'hate/threatening' => true,
        ])));
        $verdict = $this->moderator->moderate('hostile text');

        self::assertTrue($verdict->flagged);
        self::assertSame(['harassment', 'hate/threatening'], $verdict->flaggedCategories);
    }

    public function test_category_names_and_counts_stay_bounded(): void
    {
        $categories = [str_repeat('x', 200) => true];
        for ($i = 0; $i < 50; $i++) {
            $categories['cat-' . $i] = true;
        }
        $this->transport->queue(new OpenAiTransportResponse(200, $this->moderationJson(true, $categories)));

        $verdict = $this->moderator->moderate('text');
        self::assertLessThanOrEqual(32, count($verdict->flaggedCategories));
        foreach ($verdict->flaggedCategories as $name) {
            self::assertLessThanOrEqual(64, strlen($name));
        }
    }

    public function test_non_200_statuses_fail_closed_as_moderation_transport(): void
    {
        foreach ([400, 401, 429, 500] as $status) {
            $this->transport->queue(new OpenAiTransportResponse($status, ['error' => ['message' => 'boom']]));
            try {
                $this->moderator->moderate('text');
                self::fail('expected moderation_transport for status ' . $status);
            } catch (ThreadIntelligenceProviderException $e) {
                self::assertSame(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT, $e->safeCode());
                self::assertSame(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT, $e->getMessage(), 'body-free');
            }
        }
    }

    public function test_transport_exceptions_are_reclassified_as_moderation_transport(): void
    {
        $failing = new class implements \App\Service\ThreadIntelligence\OpenAiTransport {
            public function post(string $path, array $payload, int $timeoutSeconds): OpenAiTransportResponse
            {
                throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT);
            }
        };

        try {
            (new OpenAiThreadIntelligenceOutputModerator($failing))->moderate('text');
            self::fail('expected moderation_transport');
        } catch (ThreadIntelligenceProviderException $e) {
            self::assertSame(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT, $e->safeCode());
        }
    }

    public function test_malformed_moderation_bodies_fail_closed(): void
    {
        foreach ([null, [], ['results' => []], ['results' => [['no_flagged_key' => true]]]] as $body) {
            $this->transport->queue(new OpenAiTransportResponse(200, $body));
            try {
                $this->moderator->moderate('text');
                self::fail('expected moderation_transport for malformed body');
            } catch (ThreadIntelligenceProviderException $e) {
                self::assertSame(ThreadIntelligenceFailureCode::MODERATION_TRANSPORT, $e->safeCode());
            }
        }
    }
}
