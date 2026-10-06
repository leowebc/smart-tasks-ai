<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
class Document
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private string $originalName = '';

    #[ORM\Column(name: 'stored_name', type: 'string', length: 255)]
    private string $storedName = '';

    #[ORM\Column(name: 'mime_type', type: 'string', length: 127)]
    private string $mimeType = '';

    #[ORM\Column(type: 'string', length: 8)]
    private string $extension = '';

    #[ORM\Column(name: 'size_bytes', type: 'integer')]
    private int $sizeBytes = 0;

    #[ORM\Column(name: 'source_url', type: 'string', length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\ManyToOne(targetEntity: SourceImport::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'import_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?SourceImport $import = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PROCESSING;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'chunk_count', type: 'integer')]
    private int $chunkCount = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /** @var Collection<int, DocumentChunk> */
    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentChunk::class, orphanRemoval: true)]
    #[ORM\OrderBy(['chunkIndex' => 'ASC'])]
    private Collection $chunks;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->chunks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): self
    {
        $this->storedName = $storedName;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getImport(): ?SourceImport
    {
        return $this->import;
    }

    public function setImport(?SourceImport $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getChunkCount(): int
    {
        return $this->chunkCount;
    }

    public function setChunkCount(int $chunkCount): self
    {
        $this->chunkCount = $chunkCount;

        return $this;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, DocumentChunk> */
    public function getChunks(): Collection
    {
        return $this->chunks;
    }

    public function toArray(bool $withChunks = false): array
    {
        $data = [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'extension' => $this->extension,
            'size_bytes' => $this->sizeBytes,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'chunk_count' => $this->chunkCount,
            'source_url' => $this->sourceUrl,
            'import_id' => $this->import?->getId(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
        if ($withChunks) {
            $data['chunks'] = array_map(
                static fn (DocumentChunk $chunk): array => $chunk->toArray(),
                $this->chunks->toArray(),
            );
        }

        return $data;
    }
}
