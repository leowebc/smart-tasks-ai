<?php

namespace App\Service;

use App\Dto\TaskData;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\TaskNotFoundException;
use App\Repository\TaskRepository;

class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
    ) {
    }

    /**
     * @return Task[]
     */
    public function list(User $owner): array
    {
        return $this->tasks->findByOwner($owner);
    }

    public function create(User $owner, TaskData $data): Task
    {
        $task = new Task();
        $task->setUser($owner);
        $this->apply($task, $data);
        $this->tasks->save($task);

        return $task;
    }

    public function update(User $owner, int $id, TaskData $data): Task
    {
        $task = $this->requireOwned($owner, $id);
        $this->apply($task, $data);
        $this->tasks->save($task);

        return $task;
    }

    public function delete(User $owner, int $id): void
    {
        $this->tasks->remove($this->requireOwned($owner, $id));
    }

    private function requireOwned(User $owner, int $id): Task
    {
        $task = $this->tasks->findOneForOwner($id, $owner);
        if (!$task instanceof Task) {
            throw new TaskNotFoundException('Tarefa não encontrada.');
        }

        return $task;
    }

    private function apply(Task $task, TaskData $data): void
    {
        $title = trim($data->title);
        if ($title === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }
        if (strlen($title) > 255) {
            throw new \InvalidArgumentException('Título deve ter no máximo 255 caracteres.');
        }

        $description = $data->description;
        if ($description !== null) {
            $description = trim($description);
            if ($description === '') {
                $description = null;
            }
        }

        $task->setTitle($title);
        $task->setDescription($description);

        if ($data->status !== null) {
            $allowedStatuses = [
                Task::STATUS_PENDING,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_COMPLETED,
            ];
            if (!in_array($data->status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException('Status inválido.');
            }
            $task->setStatus($data->status);
        }
    }
}
