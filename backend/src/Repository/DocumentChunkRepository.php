<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentChunk;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentChunkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentChunk::class);
    }

    public function add(DocumentChunk $chunk): void
    {
        $this->getEntityManager()->persist($chunk);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * @param list<int> $documentIds
     * @return iterable<array{document_id: int, chunk_index: int, content: string, embedding: list<float>}>
     */
    public function iterateCandidates(
        array $documentIds,
        string $embeddingModel,
        int $batchSize = 25,
    ): iterable
    {
        if ($documentIds === []) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();
        $batchSize = max(1, min(100, $batchSize));
        foreach (array_chunk($documentIds, $batchSize) as $batch) {
            $result = $connection->executeQuery(
                <<<'SQL'
SELECT document_id, chunk_index, content, embedding
FROM document_chunks
WHERE document_id IN (?) AND embedding_model = ?
ORDER BY document_id ASC, chunk_index ASC
SQL,
                [$batch, $embeddingModel],
                [ArrayParameterType::INTEGER, ParameterType::STRING],
            );
            while (($row = $result->fetchAssociative()) !== false) {
                try {
                    $embedding = json_decode((string) $row['embedding'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new \RuntimeException('Um embedding armazenado é inválido.', 0, $exception);
                }
                if (!is_array($embedding)) {
                    throw new \RuntimeException('Um embedding armazenado é inválido.');
                }
                yield [
                    'document_id' => (int) $row['document_id'],
                    'chunk_index' => (int) $row['chunk_index'],
                    'content' => (string) $row['content'],
                    'embedding' => array_map(static fn (mixed $value): float => (float) $value, $embedding),
                ];
            }
        }
    }

    public function deleteByDocument(Document $document): void
    {
        $this->createQueryBuilder('chunk')
            ->delete()
            ->andWhere('chunk.document = :document')
            ->setParameter('document', $document)
            ->getQuery()
            ->execute();
    }
}
