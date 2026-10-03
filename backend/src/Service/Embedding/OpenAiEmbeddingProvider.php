<?php

namespace App\Service\Embedding;

final class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function model(): string
    {
        return $this->model;
    }

    public function embed(array $texts): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('A chave de embeddings não está configurada.');
        }
        if ($texts === []) {
            return [];
        }

        $vectors = [];
        foreach (array_chunk($texts, 64) as $offset => $batch) {
            $batchVectors = $this->request($batch);
            foreach ($batchVectors as $index => $vector) {
                $vectors[($offset * 64) + $index] = $vector;
            }
        }
        ksort($vectors);

        return array_values($vectors);
    }

    /**
     * @param list<string> $texts
     * @return array<int, list<float>>
     */
    private function request(array $texts): array
    {
        $body = json_encode([
            'model' => $this->model,
            'input' => array_values($texts),
        ], JSON_UNESCAPED_UNICODE);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer ".$this->apiKey."\r\n",
                'content' => $body,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(self::ENDPOINT, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        if ($response === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException('A API de embeddings falhou.');
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || !is_array($payload['data'] ?? null)) {
            throw new \RuntimeException('A API de embeddings devolveu uma resposta inválida.');
        }

        $vectors = [];
        foreach ($payload['data'] as $item) {
            if (!is_array($item) || !isset($item['index'], $item['embedding']) || !is_array($item['embedding'])) {
                throw new \RuntimeException('A API de embeddings devolveu um vetor inválido.');
            }
            $vectors[(int) $item['index']] = array_map(static fn (mixed $value): float => (float) $value, $item['embedding']);
        }

        return $vectors;
    }
}
