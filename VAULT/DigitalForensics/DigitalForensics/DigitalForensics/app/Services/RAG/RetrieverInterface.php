<?php

declare(strict_types=1);

namespace App\Services\RAG;

interface RetrieverInterface
{
    /**
     * @return list<ProvenanceEvidence>
     */
    public function retrieve(string $query, int $limit = 5): array;
}
