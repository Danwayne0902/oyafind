<?php

namespace App\Services\Ingestion;

use App\Models\Source;

interface SourceAdapter
{
    /**
     * Pull raw candidate records from a source.
     *
     * Implementations return plain arrays so the normaliser, not the network
     * layer, owns cleanliness, deduplication and category/location mapping.
     *
     * @return list<array<string, mixed>>
     */
    public function fetch(Source $source, int $limit): array;
}
