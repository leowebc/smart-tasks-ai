<?php

namespace App\Entity;

use App\Repository\DocumentChunkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentChunkRepository::class)]
#[ORM\Table(name: 'document_chunks')]
#[ORM\UniqueConstraint(name: 'uniq_chunk_document_index', columns: ['document_id', 'chunk_index'])]
class DocumentChunk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'chunks')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Document $document = null;

    #[ORM\Column(name: 'chunk_index', type: 'integer')]
    private int $chunkIndex = 0;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    /** @var list<float> */
    #[ORM\Column(type: 'json')]
    private array $embedding = [];

    #[ORM\Column(name: 'embedding_model', type: 'string', length: 100)]
    private string $embeddingModel = '';

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'source_metadata', type: 'json')]
    private array $sourceMetadata = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function setChunkIndex(int $chunkIndex): self
    {
        $this->chunkIndex = $chunkIndex;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /** @return list<float> */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /** @param list<float> $embedding */
    public function setEmbedding(array $embedding): self
    {
        $this->embedding = $embedding;

        return $this;
    }

    public function getEmbeddingModel(): string
    {
        return $this->embeddingModel;
    }

    public function setEmbeddingModel(string $embeddingModel): self
    {
        $this->embeddingModel = $embeddingModel;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSourceMetadata(): array
    {
        return $this->sourceMetadata;
    }

    /** @param array<string, mixed> $sourceMetadata */
    public function setSourceMetadata(array $sourceMetadata): self
    {
        $this->sourceMetadata = $sourceMetadata;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'chunk_index' => $this->chunkIndex,
            'content' => $this->content,
            'embedding_model' => $this->embeddingModel,
            'embedding_dimensions' => count($this->embedding),
            'source' => $this->sourceMetadata,
        ];
    }
}
