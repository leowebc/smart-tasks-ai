<?php

namespace App\Service\Embedding;

interface EmbeddingProviderInterface
{
    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    public function model(): string;
}
