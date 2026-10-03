<?php

namespace App\Controller;

use App\Dto\TaskData;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\TaskNotFoundException;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {
    }

    #[Route('/api/tasks', name: 'get_tasks', methods: ['GET'])]
    public function listTasks(): JsonResponse
    {
        $tasks = array_map(
            static fn (Task $task): array => $task->toArray(),
            $this->tasks->list($this->currentUser()),
        );

        return new JsonResponse($tasks);
    }

    #[Route('/api/tasks', name: 'create_task', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        try {
            $task = $this->tasks->create($this->currentUser(), $this->taskData($request));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        return new JsonResponse($task->toArray(), 201);
    }

    #[Route('/api/tasks/{id}', name: 'update_task', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateTask(int $id, Request $request): JsonResponse
    {
        try {
            $task = $this->tasks->update($this->currentUser(), $id, $this->taskData($request));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (TaskNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse($task->toArray());
    }

    #[Route('/api/tasks/{id}', name: 'delete_task', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteTask(int $id): JsonResponse
    {
        try {
            $this->tasks->delete($this->currentUser(), $id);
        } catch (TaskNotFoundException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        }

        return new JsonResponse(['status' => 'Task deleted']);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function taskData(Request $request): TaskData
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('JSON inválido.');
        }
        if (!isset($payload['title']) || !is_string($payload['title'])) {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $description = $payload['description'] ?? null;
        if ($description !== null && !is_string($description)) {
            throw new \InvalidArgumentException('Descrição inválida.');
        }

        return new TaskData($payload['title'], $description);
    }
}
