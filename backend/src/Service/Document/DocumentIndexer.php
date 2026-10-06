<?php

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\DocumentChunk;
use App\Repository\DocumentChunkRepository;
use App\Repository\DocumentRepository;
use App\Service\Embedding\EmbeddingProviderInterface;

class DocumentIndexer
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentChunkRepository $chunks,
        private readonly TextChunker $chunker,
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly int $chunkSize,
        private readonly int $chunkOverlap,
    ) {
    }

    /** @param array<string, scalar|null> $metadata */
    public function index(Document $document, string $text, array $metadata = []): Document
    {
        try {
            $pieces = $this->chunker->split($text, $this->chunkSize, $this->chunkOverlap);
            if ($pieces === []) {
                throw new \RuntimeException('Não foi possível dividir o texto.');
            }
            $vectors = $this->embeddings->embed(array_column($pieces, 'content'));
            $this->assertVectors($vectors, count($pieces));
            $this->chunks->deleteByDocument($document);
            foreach ($pieces as $index => $piece) {
                $chunk = new DocumentChunk();
                $chunk->setDocument($document);
                $chunk->setChunkIndex($index);
                $chunk->setContent($piece['content']);
                $chunk->setEmbedding($vectors[$index]);
                $chunk->setEmbeddingModel($this->embeddings->model());
                $chunk->setSourceMetadata($metadata + [
                    'original_name' => $document->getOriginalName(),
                    'source_url' => $document->getSourceUrl(),
                    'char_start' => $piece['start'],
                    'char_end' => $piece['end'],
                ]);
                $this->chunks->add($chunk);
            }
            $this->chunks->flush();
            $document->setChunkCount(count($pieces));
            $document->setStatus(Document::STATUS_READY);
            $document->setErrorMessage(null);
        } catch (\Throwable $exception) {
            $this->chunks->deleteByDocument($document);
            $document->setStatus(Document::STATUS_FAILED);
            $document->setChunkCount(0);
            $document->setErrorMessage($this->safeMessage($exception));
        }
        $document->touch();
        $this->documents->save($document);

        return $document;
    }

    /**
     * @param list<list<float>> $vectors
     */
    private function assertVectors(array $vectors, int $expected): void
    {
        if (count($vectors) !== $expected) {
            throw new \RuntimeException('A API de embeddings não devolveu todos os trechos.');
        }
        $dimension = count($vectors[0] ?? []);
        if ($dimension < 8) {
            throw new \RuntimeException('A API de embeddings devolveu um vetor inválido.');
        }
        foreach ($vectors as $vector) {
            if (count($vector) !== $dimension) {
                throw new \RuntimeException('As dimensões dos embeddings não são consistentes.');
            }
            $norm = 0.0;
            foreach ($vector as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new \RuntimeException('A API de embeddings devolveu valores inválidos.');
                }
                $number = (float) $value;
                if (!is_finite($number)) {
                    throw new \RuntimeException('A API de embeddings devolveu valores inválidos.');
                }
                $norm += $number * $number;
            }
            if ($norm <= 0.0) {
                throw new \RuntimeException('A API de embeddings devolveu um vetor vazio.');
            }
        }
    }

    private function safeMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'Falha ao processar o documento.';
        }

        return mb_substr($message, 0, 500);
    }
}
