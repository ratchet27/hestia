<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\TaskPriority;
use App\Exception\Task\TaskNotFoundException;
use App\Repository\TaskRepository;
use App\Request\SaveTaskRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository
    ) {
    }

    /**
     * @param string $status 'active'|'completed'|'all'
     * @return Task[]
     */
    public function listTasks(string $status = 'active'): array
    {
        return $this->taskRepository->findByStatus($status);
    }

    public function getTask(Uuid $id): Task
    {
        $task = $this->taskRepository->find($id);

        if ($task === null) {
            throw new TaskNotFoundException($id);
        }

        return $task;
    }

    public function createTask(SaveTaskRequest $request): Task
    {
        $task = new Task();
        $task->setName($request->name);
        $task->setPriority(TaskPriority::from($request->priority));

        if ($request->due_date !== null) {
            $task->setDueDate(new \DateTimeImmutable($request->due_date));
        }

        $this->em->persist($task);
        $this->em->flush();

        return $task;
    }

    public function updateTask(Uuid $id, SaveTaskRequest $request): Task
    {
        $task = $this->getTask($id);
        $task->setName($request->name);
        $task->setPriority(TaskPriority::from($request->priority));

        if ($request->due_date !== null) {
            $task->setDueDate(new \DateTimeImmutable($request->due_date));
        } else {
            $task->setDueDate(null);
        }

        $this->em->flush();

        return $task;
    }

    public function deleteTask(Uuid $id): void
    {
        $task = $this->getTask($id);
        $this->em->remove($task);
        $this->em->flush();
    }

    public function toggleTaskDone(Uuid $id): Task
    {
        $task = $this->getTask($id);
        $task->setDone(!$task->isDone());

        $this->em->flush();

        return $task;
    }
}
