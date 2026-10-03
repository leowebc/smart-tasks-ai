<?php

namespace App\Service\Document;

use App\Entity\Document;
use App\Entity\User;
use App\Exception\DocumentNotFoundException;
use App\Repository\DocumentChunkRepository;
use App\Repository\DocumentRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DocumentUploadService
{
    private const MAX_LABEL = '10 MB';

    /** @param iterable<TextExtractorInterface> $extractors */
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly DocumentChunkRepository $chunks,
        private readonly DocumentIndexer $indexer,
        private readonly string $storageDirectory,
        private readonly int $maxBytes,
        private readonly iterable $extractors,
    ) {
    }

    public function upload(User $owner, UploadedFile $file): Document
    {
        [$extension, $mime] = $this->assertFile($file);
        $document = new Document();
        $document->setUser($owner);
        $document->setOriginalName($this->originalName($file));
        $document->setExtension($extension);
        $document->setMimeType($mime);
        $document->setSizeBytes((int) $file->getSize());
        $document->setStoredName(bin2hex(random_bytes(16)).'.'.$extension);
        $document->setStatus(Document::STATUS_PROCESSING);
        $this->store($file, $document->getStoredName());
        $this->documents->save($document);

        return $this->process($document);
    }

    public function retry(User $owner, int $id): Document
    {
        $document = $this->requireOwned($owner, $id);
        if ($document->getStatus() !== Document::STATUS_FAILED) {
            throw new \InvalidArgumentException('Somente documentos com falha podem ser reprocessados.');
        }
        $this->chunks->deleteByDocument($document);
        $document->setStatus(Document::STATUS_PROCESSING);
        $document->setErrorMessage(null);
        $document->setChunkCount(0);
        $this->documents->save($document);

        return $this->process($document);
    }

    /** @return Document[] */
    public function list(User $owner): array
    {
        return $this->documents->findFilesByOwner($owner);
    }

    public function get(User $owner, int $id): Document
    {
        return $this->requireOwned($owner, $id);
    }

    public function delete(User $owner, int $id): void
    {
        $document = $this->requireOwned($owner, $id);
        if ($document->getSourceUrl() !== null) {
            throw new DocumentNotFoundException('Documento não encontrado.');
        }
        $path = $this->storageDirectory.'/'.$document->getStoredName();
        if (is_file($path)) {
            unlink($path);
        }
        $this->documents->remove($document);
    }

    private function process(Document $document): Document
    {
        try {
            $text = $this->extract($document);
        } catch (\Throwable $exception) {
            $this->chunks->deleteByDocument($document);
            $document->setStatus(Document::STATUS_FAILED);
            $document->setChunkCount(0);
            $document->setErrorMessage($this->safeMessage($exception));
            $document->touch();
            $this->documents->save($document);

            return $document;
        }

        return $this->indexer->index($document, $text);
    }

    /** @return array{0: string, 1: string} */
    private function assertFile(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            $code = $file->getError();
            if ($code === \UPLOAD_ERR_INI_SIZE || $code === \UPLOAD_ERR_FORM_SIZE) {
                throw new \InvalidArgumentException('O arquivo passa do limite aceito pelo servidor.');
            }
            throw new \InvalidArgumentException('Não foi possível receber o arquivo.');
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'txt'], true)) {
            throw new \InvalidArgumentException('Envie um arquivo PDF ou TXT.');
        }
        $size = (int) $file->getSize();
        if ($size < 1 || $size > $this->maxBytes) {
            throw new \InvalidArgumentException('O arquivo deve ter entre 1 byte e '.self::MAX_LABEL.'.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()) ?: '';
        $allowed = [
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
        ];
        if (!in_array($mime, $allowed[$extension], true)) {
            throw new \InvalidArgumentException('O tipo do arquivo não corresponde a PDF ou TXT.');
        }

        return [$extension, $mime];
    }

    private function store(UploadedFile $file, string $storedName): void
    {
        if (!is_dir($this->storageDirectory) && !mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)) {
            throw new \RuntimeException('Não foi possível criar a pasta de documentos.');
        }
        $file->move($this->storageDirectory, $storedName);
    }

    private function extract(Document $document): string
    {
        $path = $this->storageDirectory.'/'.$document->getStoredName();
        if (!is_file($path)) {
            throw new \RuntimeException('O arquivo armazenado não foi encontrado.');
        }
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($document->getExtension())) {
                return $extractor->extract($path);
            }
        }

        throw new \RuntimeException('Não há extrator para esse tipo de arquivo.');
    }

    private function requireOwned(User $owner, int $id): Document
    {
        $document = $this->documents->findOneForOwner($id, $owner);
        if (!$document instanceof Document) {
            throw new DocumentNotFoundException('Documento não encontrado.');
        }

        return $document;
    }

    private function originalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return $name === '' ? 'documento' : mb_substr($name, 0, 255);
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
