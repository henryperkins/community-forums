<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * The one strict structured-output schema (ADR 0019). Every object level sets
 * additionalProperties:false and requires every property, matching the OpenAI
 * strict json_schema contract; the local validator re-enforces the same shape
 * so schema enforcement never depends on the provider alone.
 */
final class ThreadIntelligenceSchema
{
    private function __construct()
    {
    }

    /** @return array<string,mixed> the Responses API `text.format` document */
    public static function responseFormat(): array
    {
        $citedItem = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['markdown', 'source_post_ids'],
            'properties' => [
                'markdown' => ['type' => 'string'],
                'source_post_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
        ];

        return [
            'type' => 'json_schema',
            'name' => 'thread_intelligence_brief',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['overview', 'key_points', 'open_questions', 'related_topics'],
                'properties' => [
                    'overview' => $citedItem,
                    'key_points' => [
                        'type' => 'array',
                        'items' => $citedItem,
                    ],
                    'open_questions' => [
                        'type' => 'array',
                        'items' => $citedItem,
                    ],
                    'related_topics' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['thread_id', 'explanation'],
                            'properties' => [
                                'thread_id' => ['type' => 'integer'],
                                'explanation' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
