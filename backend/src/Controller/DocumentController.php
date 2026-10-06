<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Exception\DocumentNotFoundException;
use App\Service\Document\DocumentUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentUploadService $uploads,
    ) {
    }

    #[Route('/api/documents', name: 'list_documents', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $documents = array_map(
            static fn (Document $document): array => $document->toArray(),
            $this->uploads->list($this->currentUser()),
        );

        return new JsonResponse($documents);
    }

    #[Route('/api/documents', name: 'create_document', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'Arquivo obrigatório.'], 400);
        }

        try {
            $document = $this->uploads->upload($this->currentUser(), $file);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 500);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Não foi possível processar o arquivo.'], 500);
        }

        $status = $document->getStatus() === Document::STATUS_READY ? 201 : 422;

        return new JsonResponse($document->toArray(), $status);
    }

    #[Route('/api/documents/{id}', name: 'get_document', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $document = $this->uploads->get($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse($document->toArray(true));
    }

    #[Route('/api/documents/{id}', name: 'delete_document', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->uploads->delete($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['status' => 'Documento excluído']);
    }

    #[Route('/api/documents/{id}/retry', name: 'retry_document', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $id): JsonResponse
    {
        try {
            $document = $this->uploads->retry($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $status = $document->getStatus() === Document::STATUS_READY ? 200 : 422;

        return new JsonResponse($document->toArray(), $status);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
