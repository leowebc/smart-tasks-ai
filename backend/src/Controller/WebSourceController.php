<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Exception\DocumentNotFoundException;
use App\Service\Scraping\WebSourceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WebSourceController extends AbstractController
{
    public function __construct(private readonly WebSourceService $sources)
    {
    }

    #[Route('/api/sources', name: 'list_sources', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->sources->catalog($this->currentUser()));
    }

    #[Route('/api/sources', name: 'create_source', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $url = is_array($payload) ? ($payload['url'] ?? null) : null;
        $followLinks = is_array($payload) && ($payload['follow_links'] ?? false) === true;
        if (!is_string($url) || trim($url) === '') {
            return new JsonResponse(['error' => 'Informe uma URL HTTP ou HTTPS.'], 400);
        }

        try {
            $documents = $this->sources->importMany($this->currentUser(), $url, $followLinks);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }

        if ($followLinks) {
            return new JsonResponse([
                'pages' => array_map(static fn (Document $document): array => $document->toArray(), $documents),
            ]);
        }

        $document = $documents[0];
        $status = $document->getStatus() === Document::STATUS_READY ? 201 : 422;

        return new JsonResponse($document->toArray(), $status);
    }

    #[Route('/api/source-imports/{id}', name: 'delete_source_import', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteGroup(int $id): JsonResponse
    {
        try {
            $this->sources->deleteGroup($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['status' => 'Importação excluída']);
    }

    #[Route('/api/sources/{id}', name: 'delete_source', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->sources->delete($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['status' => 'Fonte excluída']);
    }

    #[Route('/api/sources/{id}/retry', name: 'retry_source', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $id): JsonResponse
    {
        try {
            $document = $this->sources->retry($this->currentUser(), $id);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
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
