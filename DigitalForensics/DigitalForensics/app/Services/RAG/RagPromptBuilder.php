<?php

declare(strict_types=1);

namespace App\Services\RAG;

final class RagPromptBuilder
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>  $evidence
     * @return list<array{role: string, content: string}>
     */
    public static function injectEvidence(array $messages, array $evidence): array
    {
        if ($evidence === []) {
            return $messages;
        }

        $evidenceMessage = [
            'role' => 'system',
            'content' => self::evidenceContent($evidence),
        ];

        $insertAt = 0;
        while (($messages[$insertAt]['role'] ?? null) === 'system') {
            $insertAt++;
        }

        array_splice($messages, $insertAt, 0, [$evidenceMessage]);

        return $messages;
    }

    /**
     * @param  list<array{source_id: string, source_title: string, excerpt: string, confidence: float, retrieved_at: string}>  $evidence
     */
    private static function evidenceContent(array $evidence): string
    {
        $lines = [
            'Retrieved evidence for this response:',
            'Use this evidence when answering. If evidence is missing or low confidence, say that the answer is low confidence.',
        ];

        foreach ($evidence as $index => $item) {
            $lines[] = sprintf(
                '[%d] source_id=%s; source_title=%s; confidence=%.2f; retrieved_at=%s; excerpt=%s',
                $index + 1,
                $item['source_id'],
                $item['source_title'],
                $item['confidence'],
                $item['retrieved_at'],
                $item['excerpt'],
            );
        }

        return implode("\n", $lines);
    }
}
