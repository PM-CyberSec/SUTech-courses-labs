<?php

declare(strict_types=1);

namespace App\Services\RAG;

final class ProvenanceResponseSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'sources' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_id' => ['type' => 'string'],
                            'source_title' => ['type' => 'string'],
                            'excerpt' => ['type' => 'string'],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'retrieved_at' => ['type' => 'string'],
                        ],
                        'required' => [
                            'source_id',
                            'source_title',
                            'excerpt',
                            'confidence',
                            'retrieved_at',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['answer', 'confidence', 'sources'],
            'additionalProperties' => false,
        ];
    }
}
