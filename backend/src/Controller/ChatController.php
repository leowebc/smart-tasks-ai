<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\DocumentNotFoundException;
use App\Service\Chat\RagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    public function __construct(private readonly RagService $rag)
    {
    }

    #[Route('/api/chat', name: 'create_chat', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Informe a pergunta e as fontes.'], 400);
        }
        $question = $payload['question'] ?? null;
        $sourceIds = $payload['source_ids'] ?? null;
        if (!is_string($question) || !is_array($sourceIds)) {
            return new JsonResponse(['error' => 'Informe a pergunta e as fontes.'], 400);
        }

        try {
            $result = $this->rag->answer($this->currentUser(), $question, $sourceIds);
        } catch (DocumentNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 502);
        }

        return new JsonResponse($result);
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
