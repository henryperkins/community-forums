<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\ArrayOpenAiTransport;
use App\Service\ThreadIntelligence\OpenAiThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\OpenAiTransportResponse;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePost;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligencePromptBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligenceRelatedCandidate;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceSchema;
use PHPUnit\Framework\TestCase;

/**
 * Pins the production Responses API integration (plan Task 3): the exact
 * request payload (store:false, no tools, strict schema, HMAC safety
 * identifier), completed/incomplete parsing, usage extraction, and the safe
 * body-free error classification. Network-free via ArrayOpenAiTransport.
 */
final class OpenAiThreadIntelligenceProviderTest extends TestCase
{
    private const APP_KEY = 'unit-test-app-key-0123456789abcdef';

    private ArrayOpenAiTransport $transport;
    private OpenAiThreadIntelligenceProvider $provider;

    protected function setUp(): void
    {
        $this->transport = new ArrayOpenAiTransport();
        $this->provider = new OpenAiThreadIntelligenceProvider(
            $this->transport,
            ThreadIntelligenceConfig::fromArray(['api_key' => 'sk-test-key']),
            new ThreadIntelligencePromptBuilder(),
            self::APP_KEY,
        );
    }

    private function request(): ThreadIntelligenceRequest
    {
        return new ThreadIntelligenceRequest(
            threadId: 55,
            threadTitle: 'A public topic',
            baseline: null,
            carryForward: null,
            posts: [new ThreadIntelligenceEvidencePost(11, '2026-07-10T09:00:00Z', 'speaker-1', 'Opening post.')],
            candidates: [new ThreadIntelligenceRelatedCandidate(201, 'Other topic', 'Excerpt', [], 0, 0.4, 1, '2026-07-01T00:00:00Z')],
            sourceSnapshotHash: str_repeat('12', 32),
            promptVersion: ThreadIntelligencePromptBuilder::VERSION,
            windowNumber: 0,
            windowCount: 1,
        );
    }

    /** @param array<string,mixed> $structured */
    private function completedJson(array $structured = ['ok' => true]): array
    {
        return [
            'id' => 'resp_abc123',
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                ['type' => 'message', 'role' => 'assistant', 'content' => [
                    ['type' => 'output_text', 'text' => json_encode($structured, JSON_THROW_ON_ERROR)],
                ]],
            ],
            'usage' => [
                'input_tokens' => 1200,
                'output_tokens' => 340,
                'output_tokens_details' => ['reasoning_tokens' => 64],
                'input_tokens_details' => ['cached_tokens' => 256],
            ],
        ];
    }

    private function expectFailure(string $code, ?callable $extra = null): void
    {
        try {
            $this->provider->generate($this->request());
            self::fail('expected ' . $code);
        } catch (ThreadIntelligenceProviderException $e) {
            self::assertSame($code, $e->safeCode());
            self::assertSame($code, $e->getMessage(), 'exception strings must be body-free');
            if ($extra !== null) {
                $extra($e);
            }
        }
    }

    // ---- request shape -----------------------------------------------------

    public function test_generation_sends_exactly_the_pinned_payload_to_v1_responses(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->completedJson()));
        $request = $this->request();
        $this->provider->generate($request);

        $sent = $this->transport->requests();
        self::assertCount(1, $sent);
        self::assertSame('/v1/responses', $sent[0]['path']);
        self::assertSame(60, $sent[0]['timeout'], 'generation uses the configured 60-second timeout');

        $expectedIdentifier = hash_hmac('sha256', 'retroboards-thread-intelligence-site', self::APP_KEY);
        self::assertSame([
            'model' => 'gpt-5.6-luna',
            'reasoning' => ['effort' => 'low'],
            'store' => false,
            'tools' => [],
            'max_output_tokens' => 16000,
            'safety_identifier' => $expectedIdentifier,
            'text' => ['format' => ThreadIntelligenceSchema::responseFormat()],
            'input' => (new ThreadIntelligencePromptBuilder())->build($request),
        ], $sent[0]['payload']);
    }

    public function test_safety_identifier_is_site_scoped_not_member_or_thread_scoped(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->completedJson()));
        $this->provider->generate($this->request());

        $identifier = $this->transport->requests()[0]['payload']['safety_identifier'];
        self::assertSame(hash_hmac('sha256', 'retroboards-thread-intelligence-site', self::APP_KEY), $identifier);
        self::assertDoesNotMatchRegularExpression('/\A(?:55|11)\z/', $identifier, 'never a raw thread/member id');
        self::assertStringNotContainsString(self::APP_KEY, $identifier, 'the app key itself never leaves the site');
    }

    // ---- successful parsing ----------------------------------------------------

    public function test_completed_response_yields_decoded_output_response_id_and_usage(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, $this->completedJson(['overview' => 'x'])));
        $result = $this->provider->generate($this->request());

        self::assertSame(['overview' => 'x'], $result->output);
        self::assertSame('resp_abc123', $result->responseId);
        self::assertSame('completed', $result->status);
        self::assertSame(1200, $result->usage->inputTokens);
        self::assertSame(340, $result->usage->outputTokens);
        self::assertSame(64, $result->usage->reasoningTokens);
        self::assertSame(256, $result->usage->cachedTokens);
    }

    public function test_missing_usage_counts_stay_null(): void
    {
        $json = $this->completedJson();
        unset($json['usage']);
        $this->transport->queue(new OpenAiTransportResponse(200, $json));

        $usage = $this->provider->generate($this->request())->usage;
        self::assertNull($usage->inputTokens);
        self::assertNull($usage->outputTokens);
        self::assertNull($usage->reasoningTokens);
        self::assertNull($usage->cachedTokens);
    }

    // ---- incomplete / malformed classification ------------------------------------

    public function test_max_output_tokens_incomplete_is_classified_as_output_truncated(): void
    {
        $json = $this->completedJson();
        $json['status'] = 'incomplete';
        $json['incomplete_details'] = ['reason' => 'max_output_tokens'];
        $this->transport->queue(new OpenAiTransportResponse(200, $json));

        $this->expectFailure(ThreadIntelligenceFailureCode::OUTPUT_TRUNCATED);
    }

    public function test_other_incomplete_reasons_are_schema_invalid(): void
    {
        $json = $this->completedJson();
        $json['status'] = 'incomplete';
        $json['incomplete_details'] = ['reason' => 'content_filter'];
        $this->transport->queue(new OpenAiTransportResponse(200, $json));

        $this->expectFailure(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
    }

    public function test_unparseable_or_textless_success_bodies_are_schema_invalid(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(200, null));
        $this->expectFailure(ThreadIntelligenceFailureCode::SCHEMA_INVALID);

        $noMessage = $this->completedJson();
        $noMessage['output'] = [['type' => 'reasoning', 'summary' => []]];
        $this->transport->queue(new OpenAiTransportResponse(200, $noMessage));
        $this->expectFailure(ThreadIntelligenceFailureCode::SCHEMA_INVALID);

        $badText = $this->completedJson();
        $badText['output'][1]['content'][0]['text'] = 'not json at all';
        $this->transport->queue(new OpenAiTransportResponse(200, $badText));
        $this->expectFailure(ThreadIntelligenceFailureCode::SCHEMA_INVALID);
    }

    // ---- error classification --------------------------------------------------------

    public function test_authentication_failures_block_the_provider(): void
    {
        foreach ([401, 403] as $status) {
            $this->transport->queue(new OpenAiTransportResponse($status, ['error' => ['message' => 'Incorrect API key sk-secret-do-not-log']]));
            $this->expectFailure(ThreadIntelligenceFailureCode::AUTHENTICATION, function (ThreadIntelligenceProviderException $e): void {
                self::assertTrue($e->blocksProvider());
                self::assertStringNotContainsString('sk-secret', $e->getMessage());
            });
        }
    }

    public function test_invalid_model_400_blocks_the_provider(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(400, [
            'error' => ['message' => 'The model gpt-nope does not exist', 'type' => 'invalid_request_error', 'code' => 'model_not_found', 'param' => 'model'],
        ]));
        $this->expectFailure(ThreadIntelligenceFailureCode::INVALID_MODEL, function (ThreadIntelligenceProviderException $e): void {
            self::assertTrue($e->blocksProvider());
        });
    }

    public function test_other_400s_are_transient_provider_unavailable(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(400, ['error' => ['message' => 'bad request', 'code' => 'invalid_value']]));
        $this->expectFailure(ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE, function (ThreadIntelligenceProviderException $e): void {
            self::assertFalse($e->blocksProvider());
        });
    }

    public function test_429_honors_the_bounded_retry_after(): void
    {
        $this->transport->queue(new OpenAiTransportResponse(429, ['error' => ['message' => 'rate limited']], 30));
        $this->expectFailure(ThreadIntelligenceFailureCode::RATE_LIMITED, function (ThreadIntelligenceProviderException $e): void {
            self::assertSame(30, $e->retryAfterSeconds());
            self::assertFalse($e->blocksProvider());
        });
    }

    public function test_5xx_and_unexpected_statuses_are_provider_unavailable(): void
    {
        foreach ([500, 502, 503, 404] as $status) {
            $this->transport->queue(new OpenAiTransportResponse($status, null));
            $this->expectFailure(ThreadIntelligenceFailureCode::PROVIDER_UNAVAILABLE);
        }
    }

    public function test_transport_exceptions_bubble_as_safe_transport_failures(): void
    {
        $failing = new class implements \App\Service\ThreadIntelligence\OpenAiTransport {
            public function post(string $path, array $payload, int $timeoutSeconds): OpenAiTransportResponse
            {
                throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::TRANSPORT);
            }
        };
        $provider = new OpenAiThreadIntelligenceProvider(
            $failing,
            ThreadIntelligenceConfig::fromArray([]),
            new ThreadIntelligencePromptBuilder(),
            self::APP_KEY,
        );

        try {
            $provider->generate($this->request());
            self::fail('expected transport failure');
        } catch (ThreadIntelligenceProviderException $e) {
            self::assertSame(ThreadIntelligenceFailureCode::TRANSPORT, $e->safeCode());
        }
    }

    public function test_overlong_response_ids_are_bounded(): void
    {
        $json = $this->completedJson();
        $json['id'] = str_repeat('r', 400);
        $this->transport->queue(new OpenAiTransportResponse(200, $json));

        $result = $this->provider->generate($this->request());
        self::assertNotNull($result->responseId);
        self::assertLessThanOrEqual(128, strlen($result->responseId));
    }
}
