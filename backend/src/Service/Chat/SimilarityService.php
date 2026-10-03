<?php

namespace App\Service\Chat;

final class SimilarityService
{
    /**
     * @param list<float> $query
     * @param iterable<array{document_id: int, chunk_index: int, content: string, embedding: list<float>}> $candidates
     * @return list<array{document_id: int, chunk_index: int, content: string, score: float}>
     */
    public function top(array $query, iterable $candidates, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $scored[] = [
                'document_id' => $candidate['document_id'],
                'chunk_index' => $candidate['chunk_index'],
                'content' => $candidate['content'],
                'score' => $this->cosine($query, $candidate['embedding']),
            ];
            usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
            if (count($scored) > $limit) {
                array_pop($scored);
            }
        }

        return $scored;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    public function cosine(array $left, array $right): float
    {
        $length = count($left);
        if ($length === 0 || $length !== count($right)) {
            return 0.0;
        }
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        for ($index = 0; $index < $length; $index++) {
            $a = (float) $left[$index];
            $b = (float) $right[$index];
            if (!is_finite($a) || !is_finite($b)) {
                return 0.0;
            }
            $dot += $a * $b;
            $leftNorm += $a * $a;
            $rightNorm += $b * $b;
        }
        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
