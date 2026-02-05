# Tasks & Chores Backend Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement backend API for Tasks and Chores entities following the approved design.

**Architecture:** Two separate entities (Chore, Task) with full CRUD endpoints. Chores have hybrid scheduling (interval/fixed), Tasks have priority and optional due dates.

**Tech Stack:** Symfony 7.4, Doctrine ORM, PostgreSQL, PHPUnit with Foundry

---

## Task 1: Chore Entity

**Files:**
- Create: `src/Entity/Chore.php`
- Create: `src/Enum/ScheduleType.php`

**Step 1: Create ScheduleType enum**

```php
<?php
declare(strict_types=1);

namespace App\Enum;

enum ScheduleType: string
{
    case INTERVAL = 'interval';
    case FIXED_WEEKLY = 'fixed_weekly';
    case FIXED_MONTHLY = 'fixed_monthly';
}
```

**Step 2: Create Chore entity**

```php
<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\ScheduleType;
use App\Repository\ChoreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChoreRepository::class)]
#[ORM\Table(name: 'chores')]
#[ORM\HasLifecycleCallbacks]
class Chore
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, enumType: ScheduleType::class)]
    private ScheduleType $scheduleType;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    private int $scheduleValue;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $assignee = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastDoneAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $nextDueAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->nextDueAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getScheduleType(): ScheduleType
    {
        return $this->scheduleType;
    }

    public function setScheduleType(ScheduleType $scheduleType): static
    {
        $this->scheduleType = $scheduleType;
        return $this;
    }

    public function getScheduleValue(): int
    {
        return $this->scheduleValue;
    }

    public function setScheduleValue(int $scheduleValue): static
    {
        $this->scheduleValue = $scheduleValue;
        return $this;
    }

    public function getAssignee(): ?string
    {
        return $this->assignee;
    }

    public function setAssignee(?string $assignee): static
    {
        $this->assignee = $assignee;
        return $this;
    }

    public function getLastDoneAt(): ?\DateTimeImmutable
    {
        return $this->lastDoneAt;
    }

    public function setLastDoneAt(?\DateTimeImmutable $lastDoneAt): static
    {
        $this->lastDoneAt = $lastDoneAt;
        return $this;
    }

    public function getNextDueAt(): \DateTimeImmutable
    {
        return $this->nextDueAt;
    }

    public function setNextDueAt(\DateTimeImmutable $nextDueAt): static
    {
        $this->nextDueAt = $nextDueAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markDone(\DateTimeImmutable $now): static
    {
        $this->lastDoneAt = $now;
        $this->nextDueAt = $this->calculateNextDueAt($now);
        return $this;
    }

    private function calculateNextDueAt(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return match ($this->scheduleType) {
            ScheduleType::INTERVAL => $from->modify("+{$this->scheduleValue} days"),
            ScheduleType::FIXED_WEEKLY => $this->nextWeekday($from, $this->scheduleValue),
            ScheduleType::FIXED_MONTHLY => $this->nextMonthDay($from, $this->scheduleValue),
        };
    }

    private function nextWeekday(\DateTimeImmutable $from, int $targetWeekday): \DateTimeImmutable
    {
        $currentWeekday = (int) $from->format('N');
        $daysUntil = $targetWeekday - $currentWeekday;
        if ($daysUntil <= 0) {
            $daysUntil += 7;
        }
        return $from->modify("+{$daysUntil} days");
    }

    private function nextMonthDay(\DateTimeImmutable $from, int $targetDay): \DateTimeImmutable
    {
        $currentDay = (int) $from->format('j');
        if ($currentDay < $targetDay) {
            return $from->setDate((int) $from->format('Y'), (int) $from->format('m'), $targetDay);
        }
        $nextMonth = $from->modify('first day of next month');
        return $nextMonth->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), $targetDay);
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Entity/Chore.php src/Enum/ScheduleType.php
git commit -s -m "feat(chores): add Chore entity with schedule types"
```

---

## Task 2: Task Entity

**Files:**
- Create: `src/Entity/Task.php`
- Create: `src/Enum/TaskPriority.php`

**Step 1: Create TaskPriority enum**

```php
<?php
declare(strict_types=1);

namespace App\Enum;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}
```

**Step 2: Create Task entity**

```php
<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::STRING, enumType: TaskPriority::class)]
    private TaskPriority $priority = TaskPriority::MEDIUM;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;
        if ($done && $this->doneAt === null) {
            $this->doneAt = new \DateTimeImmutable();
        } elseif (!$done) {
            $this->doneAt = null;
        }
        return $this;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Entity/Task.php src/Enum/TaskPriority.php
git commit -s -m "feat(tasks): add Task entity with priority"
```

---

## Task 3: Repositories

**Files:**
- Create: `src/Repository/ChoreRepository.php`
- Create: `src/Repository/TaskRepository.php`

**Step 1: Create ChoreRepository**

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chore>
 */
class ChoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chore::class);
    }

    /**
     * @return Chore[]
     */
    public function findAllOrderedByNextDue(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nextDueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

**Step 2: Create TaskRepository**

```php
<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.done = false')
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findCompletedRecently(int $days = 3): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        return $this->createQueryBuilder('t')
            ->where('t.done = true')
            ->andWhere('t.doneAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('t.doneAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $status 'active'|'completed'|'all'
     * @return Task[]
     */
    public function findByStatus(string $status): array
    {
        $qb = $this->createQueryBuilder('t');

        if ($status === 'active') {
            $qb->where('t.done = false');
        } elseif ($status === 'completed') {
            $since = new \DateTimeImmutable('-3 days');
            $qb->where('t.done = true')
               ->andWhere('t.doneAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Repository/ChoreRepository.php src/Repository/TaskRepository.php
git commit -s -m "feat(tasks): add Chore and Task repositories"
```

---

## Task 4: Database Migration

**Files:**
- Create: `migrations/VersionXXX.php` (auto-generated)

**Step 1: Generate migration**

Run: `docker compose exec php bin/console doctrine:migrations:diff`
Expected: Migration file created

**Step 2: Review and run migration**

Run: `docker compose exec php bin/console doctrine:migrations:migrate --no-interaction`
Expected: Migration applied

**Step 3: Commit**

```bash
git add migrations/
git commit -s -m "feat(tasks): add database migration for chores and tasks"
```

---

## Task 5: Request DTOs

**Files:**
- Create: `src/Request/CreateChoreRequest.php`
- Create: `src/Request/UpdateChoreRequest.php`
- Create: `src/Request/CreateTaskRequest.php`
- Create: `src/Request/UpdateTaskRequest.php`

**Step 1: Create CreateChoreRequest**

```php
<?php
declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateChoreRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['interval', 'fixed_weekly', 'fixed_monthly'])]
        public string $schedule_type,

        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\Range(min: 1, max: 365)]
        public int $schedule_value,

        #[Assert\Length(max: 100)]
        public ?string $assignee = null,
    ) {}
}
```

**Step 2: Create UpdateChoreRequest**

```php
<?php
declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateChoreRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['interval', 'fixed_weekly', 'fixed_monthly'])]
        public string $schedule_type,

        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\Range(min: 1, max: 365)]
        public int $schedule_value,

        #[Assert\Length(max: 100)]
        public ?string $assignee = null,
    ) {}
}
```

**Step 3: Create CreateTaskRequest**

```php
<?php
declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\Date]
        public ?string $due_date = null,

        #[Assert\Choice(choices: ['low', 'medium', 'high'])]
        public string $priority = 'medium',
    ) {}
}
```

**Step 4: Create UpdateTaskRequest**

```php
<?php
declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\Date]
        public ?string $due_date = null,

        #[Assert\Choice(choices: ['low', 'medium', 'high'])]
        public string $priority = 'medium',
    ) {}
}
```

**Step 5: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 6: Commit**

```bash
git add src/Request/CreateChoreRequest.php src/Request/UpdateChoreRequest.php src/Request/CreateTaskRequest.php src/Request/UpdateTaskRequest.php
git commit -s -m "feat(tasks): add request DTOs for chores and tasks"
```

---

## Task 6: Response DTOs

**Files:**
- Create: `src/Response/Chore/ChoreResponse.php`
- Create: `src/Response/Task/TaskResponse.php`

**Step 1: Create ChoreResponse**

```php
<?php
declare(strict_types=1);

namespace App\Response\Chore;

use App\Entity\Chore;
use Symfony\Component\Uid\Uuid;

final readonly class ChoreResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public string $schedule_type,
        public int $schedule_value,
        public ?string $assignee,
        public ?\DateTimeImmutable $last_done_at,
        public \DateTimeImmutable $next_due_at,
        public \DateTimeImmutable $created_at,
        public ?\DateTimeImmutable $updated_at,
    ) {}

    public static function fromEntity(Chore $chore): self
    {
        return new self(
            id: $chore->getId(),
            name: $chore->getName(),
            schedule_type: $chore->getScheduleType()->value,
            schedule_value: $chore->getScheduleValue(),
            assignee: $chore->getAssignee(),
            last_done_at: $chore->getLastDoneAt(),
            next_due_at: $chore->getNextDueAt(),
            created_at: $chore->getCreatedAt(),
            updated_at: $chore->getUpdatedAt(),
        );
    }
}
```

**Step 2: Create TaskResponse**

```php
<?php
declare(strict_types=1);

namespace App\Response\Task;

use App\Entity\Task;
use Symfony\Component\Uid\Uuid;

final readonly class TaskResponse
{
    public function __construct(
        public Uuid $id,
        public string $name,
        public ?\DateTimeImmutable $due_date,
        public string $priority,
        public bool $done,
        public ?\DateTimeImmutable $done_at,
        public \DateTimeImmutable $created_at,
    ) {}

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: $task->getId(),
            name: $task->getName(),
            due_date: $task->getDueDate(),
            priority: $task->getPriority()->value,
            done: $task->isDone(),
            done_at: $task->getDoneAt(),
            created_at: $task->getCreatedAt(),
        );
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Response/Chore/ src/Response/Task/
git commit -s -m "feat(tasks): add response DTOs for chores and tasks"
```

---

## Task 7: Exceptions

**Files:**
- Create: `src/Exception/Chore/ChoreNotFoundException.php`
- Create: `src/Exception/Task/TaskNotFoundException.php`

**Step 1: Create ChoreNotFoundException**

```php
<?php
declare(strict_types=1);

namespace App\Exception\Chore;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ChoreNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Chore not found',
            type: 'CHORE_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id],
        ));
    }
}
```

**Step 2: Create TaskNotFoundException**

```php
<?php
declare(strict_types=1);

namespace App\Exception\Task;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class TaskNotFoundException extends ApiException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(new ApiProblem(
            title: 'Task not found',
            type: 'TASK_NOT_FOUND',
            code: Response::HTTP_NOT_FOUND,
            extraData: ['id' => (string) $id],
        ));
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Exception/Chore/ src/Exception/Task/
git commit -s -m "feat(tasks): add exceptions for chores and tasks"
```

---

## Task 8: Services

**Files:**
- Create: `src/Service/ChoreService.php`
- Create: `src/Service/TaskService.php`

**Step 1: Create ChoreService**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Exception\Chore\ChoreNotFoundException;
use App\Repository\ChoreRepository;
use App\Request\CreateChoreRequest;
use App\Request\UpdateChoreRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ChoreService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChoreRepository $choreRepository,
    ) {}

    /**
     * @return Chore[]
     */
    public function listChores(): array
    {
        return $this->choreRepository->findAllOrderedByNextDue();
    }

    public function getChore(Uuid $id): Chore
    {
        $chore = $this->choreRepository->find($id);
        if ($chore === null) {
            throw new ChoreNotFoundException($id);
        }
        return $chore;
    }

    public function createChore(CreateChoreRequest $request): Chore
    {
        $chore = new Chore();
        $chore->setName($request->name);
        $chore->setScheduleType(ScheduleType::from($request->schedule_type));
        $chore->setScheduleValue($request->schedule_value);
        $chore->setAssignee($request->assignee);

        $this->em->persist($chore);
        $this->em->flush();

        return $chore;
    }

    public function updateChore(Uuid $id, UpdateChoreRequest $request): Chore
    {
        $chore = $this->getChore($id);
        $chore->setName($request->name);
        $chore->setScheduleType(ScheduleType::from($request->schedule_type));
        $chore->setScheduleValue($request->schedule_value);
        $chore->setAssignee($request->assignee);

        $this->em->flush();

        return $chore;
    }

    public function deleteChore(Uuid $id): void
    {
        $chore = $this->getChore($id);
        $this->em->remove($chore);
        $this->em->flush();
    }

    public function markChoreDone(Uuid $id): Chore
    {
        $chore = $this->getChore($id);
        $chore->markDone(new \DateTimeImmutable());
        $this->em->flush();

        return $chore;
    }
}
```

**Step 2: Create TaskService**

```php
<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Enum\TaskPriority;
use App\Exception\Task\TaskNotFoundException;
use App\Repository\TaskRepository;
use App\Request\CreateTaskRequest;
use App\Request\UpdateTaskRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepository $taskRepository,
    ) {}

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

    public function createTask(CreateTaskRequest $request): Task
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

    public function updateTask(Uuid $id, UpdateTaskRequest $request): Task
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
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Service/ChoreService.php src/Service/TaskService.php
git commit -s -m "feat(tasks): add services for chores and tasks"
```

---

## Task 9: Chore Controller

**Files:**
- Create: `src/Controller/Api/Internal/V1/ChoreController.php`

**Step 1: Create ChoreController**

```php
<?php
declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\CreateChoreRequest;
use App\Request\UpdateChoreRequest;
use App\Response\Chore\ChoreResponse;
use App\Service\ChoreService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Chores')]
#[Route('/api/internal/v1')]
final class ChoreController extends AbstractController
{
    public function __construct(
        private readonly ChoreService $choreService,
    ) {}

    #[Route('/chores', name: 'api_chores_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns a list of all chores ordered by next due date', summary: 'List chores')]
    #[OA\Response(response: 200, description: 'List of chores', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: ChoreResponse::class))),
        new OA\Property(property: 'meta', properties: [new OA\Property(property: 'total', type: 'integer')], type: 'object'),
    ]))]
    public function list(): JsonResponse
    {
        $chores = $this->choreService->listChores();
        $data = array_map(ChoreResponse::fromEntity(...), $chores);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/chores/{uuid}', name: 'api_chores_show', requirements: ['uuid' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(description: 'Returns a single chore by UUID', summary: 'Get chore')]
    #[OA\Response(response: 200, description: 'Chore details', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class)),
    ]))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        $chore = $this->choreService->getChore($uuid);
        return $this->json(['data' => ChoreResponse::fromEntity($chore)]);
    }

    #[Route('/chores', name: 'api_chores_create', methods: ['POST'])]
    #[OA\Post(description: 'Creates a new chore', summary: 'Create chore')]
    #[OA\Response(response: 201, description: 'Chore created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class)),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    public function create(#[MapRequestPayload] CreateChoreRequest $request): JsonResponse
    {
        $chore = $this->choreService->createChore($request);
        return $this->json(['data' => ChoreResponse::fromEntity($chore)], Response::HTTP_CREATED);
    }

    #[Route('/chores/{uuid}', name: 'api_chores_update', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PUT'])]
    #[OA\Put(description: 'Updates an existing chore', summary: 'Update chore')]
    #[OA\Response(response: 200, description: 'Chore updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class)),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateChoreRequest $request): JsonResponse
    {
        $chore = $this->choreService->updateChore($uuid, $request);
        return $this->json(['data' => ChoreResponse::fromEntity($chore)]);
    }

    #[Route('/chores/{uuid}', name: 'api_chores_delete', requirements: ['uuid' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(description: 'Deletes a chore', summary: 'Delete chore')]
    #[OA\Response(response: 204, description: 'Chore deleted')]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->choreService->deleteChore($uuid);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/chores/{uuid}/done', name: 'api_chores_done', requirements: ['uuid' => Requirement::UUID_V7], methods: ['POST'])]
    #[OA\Post(description: 'Marks a chore as done and calculates next due date', summary: 'Mark chore done')]
    #[OA\Response(response: 200, description: 'Chore marked done', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ChoreResponse::class)),
    ]))]
    #[OA\Response(response: 404, description: 'Chore not found', content: new Model(type: ApiProblem::class))]
    public function markDone(Uuid $uuid): JsonResponse
    {
        $chore = $this->choreService->markChoreDone($uuid);
        return $this->json(['data' => ChoreResponse::fromEntity($chore)]);
    }
}
```

**Step 2: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 3: Commit**

```bash
git add src/Controller/Api/Internal/V1/ChoreController.php
git commit -s -m "feat(chores): add ChoreController with CRUD endpoints"
```

---

## Task 10: Task Controller

**Files:**
- Create: `src/Controller/Api/Internal/V1/TaskController.php`

**Step 1: Create TaskController**

```php
<?php
declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\CreateTaskRequest;
use App\Request\UpdateTaskRequest;
use App\Response\Task\TaskResponse;
use App\Service\TaskService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Tasks')]
#[Route('/api/internal/v1')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    #[Route('/tasks', name: 'api_tasks_list', methods: ['GET'])]
    #[OA\Get(description: 'Returns a list of tasks', summary: 'List tasks')]
    #[OA\Parameter(name: 'status', description: 'Filter by status: active, completed, all', in: 'query', schema: new OA\Schema(type: 'string', default: 'active'))]
    #[OA\Response(response: 200, description: 'List of tasks', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: TaskResponse::class))),
        new OA\Property(property: 'meta', properties: [new OA\Property(property: 'total', type: 'integer')], type: 'object'),
    ]))]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->getString('status', 'active');
        $tasks = $this->taskService->listTasks($status);
        $data = array_map(TaskResponse::fromEntity(...), $tasks);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/tasks/{uuid}', name: 'api_tasks_show', requirements: ['uuid' => Requirement::UUID_V7], methods: ['GET'])]
    #[OA\Get(description: 'Returns a single task by UUID', summary: 'Get task')]
    #[OA\Response(response: 200, description: 'Task details', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class)),
    ]))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function show(Uuid $uuid): JsonResponse
    {
        $task = $this->taskService->getTask($uuid);
        return $this->json(['data' => TaskResponse::fromEntity($task)]);
    }

    #[Route('/tasks', name: 'api_tasks_create', methods: ['POST'])]
    #[OA\Post(description: 'Creates a new task', summary: 'Create task')]
    #[OA\Response(response: 201, description: 'Task created', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class)),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    public function create(#[MapRequestPayload] CreateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->createTask($request);
        return $this->json(['data' => TaskResponse::fromEntity($task)], Response::HTTP_CREATED);
    }

    #[Route('/tasks/{uuid}', name: 'api_tasks_update', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PUT'])]
    #[OA\Put(description: 'Updates an existing task', summary: 'Update task')]
    #[OA\Response(response: 200, description: 'Task updated', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class)),
    ]))]
    #[OA\Response(response: 400, description: 'Invalid input', content: new Model(type: ApiProblem::class))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function update(Uuid $uuid, #[MapRequestPayload] UpdateTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->updateTask($uuid, $request);
        return $this->json(['data' => TaskResponse::fromEntity($task)]);
    }

    #[Route('/tasks/{uuid}', name: 'api_tasks_delete', requirements: ['uuid' => Requirement::UUID_V7], methods: ['DELETE'])]
    #[OA\Delete(description: 'Deletes a task', summary: 'Delete task')]
    #[OA\Response(response: 204, description: 'Task deleted')]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function delete(Uuid $uuid): JsonResponse
    {
        $this->taskService->deleteTask($uuid);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/tasks/{uuid}/done', name: 'api_tasks_toggle_done', requirements: ['uuid' => Requirement::UUID_V7], methods: ['PATCH'])]
    #[OA\Patch(description: 'Toggles task done status', summary: 'Toggle task done')]
    #[OA\Response(response: 200, description: 'Task status toggled', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: TaskResponse::class)),
    ]))]
    #[OA\Response(response: 404, description: 'Task not found', content: new Model(type: ApiProblem::class))]
    public function toggleDone(Uuid $uuid): JsonResponse
    {
        $task = $this->taskService->toggleTaskDone($uuid);
        return $this->json(['data' => TaskResponse::fromEntity($task)]);
    }
}
```

**Step 2: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 3: Commit**

```bash
git add src/Controller/Api/Internal/V1/TaskController.php
git commit -s -m "feat(tasks): add TaskController with CRUD endpoints"
```

---

## Task 11: Test Factories

**Files:**
- Create: `src/Factory/ChoreFactory.php`
- Create: `src/Factory/TaskFactory.php`

**Step 1: Create ChoreFactory**

```php
<?php
declare(strict_types=1);

namespace App\Factory;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Chore>
 */
final class ChoreFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Chore::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->sentence(3),
            'scheduleType' => self::faker()->randomElement(ScheduleType::cases()),
            'scheduleValue' => self::faker()->numberBetween(1, 30),
            'assignee' => self::faker()->optional(0.5)->firstName(),
            'nextDueAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('now', '+1 month')),
        ];
    }
}
```

**Step 2: Create TaskFactory**

```php
<?php
declare(strict_types=1);

namespace App\Factory;

use App\Entity\Task;
use App\Enum\TaskPriority;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Task>
 */
final class TaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Task::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->sentence(3),
            'priority' => self::faker()->randomElement(TaskPriority::cases()),
            'dueDate' => self::faker()->optional(0.7)->dateTimeBetween('now', '+1 month'),
            'done' => false,
        ];
    }
}
```

**Step 3: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Factory/ChoreFactory.php src/Factory/TaskFactory.php
git commit -s -m "test(tasks): add factories for chores and tasks"
```

---

## Task 12: Chore Controller Tests

**Files:**
- Create: `tests/Functional/Controller/Api/Internal/V1/ChoreControllerTest.php`

**Step 1: Create test file**

```php
<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Factory\ChoreFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ChoreControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/chores');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsChores(): void
    {
        ChoreFactory::createMany(3);

        $response = $this->apiGet('/chores');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 3);
    }

    public function testShowReturnsChore(): void
    {
        $chore = ChoreFactory::createOne(['name' => 'Test Chore']);

        $response = $this->apiGet('/chores/' . $chore->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Chore', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingChore(): void
    {
        $response = $this->apiGet('/chores/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Chore not found', $data['title']);
        static::assertSame('CHORE_NOT_FOUND', $data['type']);
    }

    public function testCreateChore(): void
    {
        $response = $this->apiPost('/chores', [
            'name' => 'Clean kitchen',
            'schedule_type' => 'interval',
            'schedule_value' => 7,
            'assignee' => 'Pavel',
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Clean kitchen', $data['data']['name']);
        static::assertSame('interval', $data['data']['schedule_type']);
        static::assertSame(7, $data['data']['schedule_value']);
        static::assertSame('Pavel', $data['data']['assignee']);

        $this->assertDatabaseHas(Chore::class, ['name' => 'Clean kitchen']);
    }

    public function testCreateChoreValidation(): void
    {
        $response = $this->apiPost('/chores', [
            'name' => '',
            'schedule_type' => 'invalid',
            'schedule_value' => 0,
        ]);
        static::assertErrorResponse($response, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateChore(): void
    {
        $chore = ChoreFactory::createOne(['name' => 'Original', 'scheduleType' => ScheduleType::INTERVAL, 'scheduleValue' => 7]);

        $response = $this->apiPut('/chores/' . $chore->getId(), [
            'name' => 'Updated',
            'schedule_type' => 'fixed_weekly',
            'schedule_value' => 1,
            'assignee' => null,
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Updated', $data['data']['name']);
        static::assertSame('fixed_weekly', $data['data']['schedule_type']);
    }

    public function testDeleteChore(): void
    {
        $chore = ChoreFactory::createOne();

        $response = $this->apiDelete('/chores/' . $chore->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertDatabaseMissing(Chore::class, ['id' => $chore->getId()]);
    }

    public function testMarkChoreDone(): void
    {
        $chore = ChoreFactory::createOne([
            'scheduleType' => ScheduleType::INTERVAL,
            'scheduleValue' => 7,
        ]);
        $originalNextDue = $chore->getNextDueAt();

        $response = $this->apiPost('/chores/' . $chore->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertNotNull($data['data']['last_done_at']);
        static::assertNotEquals($originalNextDue->format('Y-m-d'), $data['data']['next_due_at']);
    }
}
```

**Step 2: Run tests**

Run: `docker compose exec php bin/phpunit tests/Functional/Controller/Api/Internal/V1/ChoreControllerTest.php`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Functional/Controller/Api/Internal/V1/ChoreControllerTest.php
git commit -s -m "test(chores): add ChoreController functional tests"
```

---

## Task 13: Task Controller Tests

**Files:**
- Create: `tests/Functional/Controller/Api/Internal/V1/TaskControllerTest.php`

**Step 1: Create test file**

```php
<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Internal\V1;

use App\Entity\Task;
use App\Enum\TaskPriority;
use App\Factory\TaskFactory;
use App\Tests\Functional\Trait\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TaskControllerTest extends WebTestCase
{
    use ApiTestTrait;
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsEmptyWhenNoData(): void
    {
        $response = $this->apiGet('/tasks');
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 0);
    }

    public function testListReturnsActiveTasks(): void
    {
        TaskFactory::createMany(2, ['done' => false]);
        TaskFactory::createOne(['done' => true, 'doneAt' => new \DateTimeImmutable()]);

        $response = $this->apiGet('/tasks', ['status' => 'active']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 2);
    }

    public function testListReturnsCompletedTasks(): void
    {
        TaskFactory::createMany(2, ['done' => false]);
        TaskFactory::createOne(['done' => true, 'doneAt' => new \DateTimeImmutable()]);

        $response = $this->apiGet('/tasks', ['status' => 'completed']);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertListResponse($data, 1);
    }

    public function testShowReturnsTask(): void
    {
        $task = TaskFactory::createOne(['name' => 'Test Task']);

        $response = $this->apiGet('/tasks/' . $task->getId());
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Test Task', $data['data']['name']);
    }

    public function testShowReturnsNotFoundForMissingTask(): void
    {
        $response = $this->apiGet('/tasks/' . Uuid::v7());
        $data = static::assertErrorResponse($response, Response::HTTP_NOT_FOUND);

        static::assertSame('Task not found', $data['title']);
        static::assertSame('TASK_NOT_FOUND', $data['type']);
    }

    public function testCreateTask(): void
    {
        $response = $this->apiPost('/tasks', [
            'name' => 'Buy milk',
            'due_date' => '2026-02-10',
            'priority' => 'high',
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertArrayHasKey('data', $data);
        static::assertSame('Buy milk', $data['data']['name']);
        static::assertSame('high', $data['data']['priority']);
        static::assertFalse($data['data']['done']);

        $this->assertDatabaseHas(Task::class, ['name' => 'Buy milk']);
    }

    public function testCreateTaskWithDefaults(): void
    {
        $response = $this->apiPost('/tasks', [
            'name' => 'Simple task',
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_CREATED);

        static::assertSame('medium', $data['data']['priority']);
        static::assertNull($data['data']['due_date']);
    }

    public function testUpdateTask(): void
    {
        $task = TaskFactory::createOne(['name' => 'Original', 'priority' => TaskPriority::LOW]);

        $response = $this->apiPut('/tasks/' . $task->getId(), [
            'name' => 'Updated',
            'priority' => 'high',
            'due_date' => '2026-03-01',
        ]);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertSame('Updated', $data['data']['name']);
        static::assertSame('high', $data['data']['priority']);
    }

    public function testDeleteTask(): void
    {
        $task = TaskFactory::createOne();

        $response = $this->apiDelete('/tasks/' . $task->getId());
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertDatabaseMissing(Task::class, ['id' => $task->getId()]);
    }

    public function testToggleTaskDone(): void
    {
        $task = TaskFactory::createOne(['done' => false]);

        $response = $this->apiPatch('/tasks/' . $task->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertTrue($data['data']['done']);
        static::assertNotNull($data['data']['done_at']);

        // Toggle back
        $response = $this->apiPatch('/tasks/' . $task->getId() . '/done', []);
        $data = static::assertJsonResponse($response, Response::HTTP_OK);

        static::assertFalse($data['data']['done']);
        static::assertNull($data['data']['done_at']);
    }
}
```

**Step 2: Run tests**

Run: `docker compose exec php bin/phpunit tests/Functional/Controller/Api/Internal/V1/TaskControllerTest.php`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Functional/Controller/Api/Internal/V1/TaskControllerTest.php
git commit -s -m "test(tasks): add TaskController functional tests"
```

---

## Task 14: Run Full Test Suite

**Step 1: Run all backend tests**

Run: `cd /home/pavel/projects/personal/hestia/backend && make test`
Expected: All tests pass (152 existing + new tests)

**Step 2: Run linter**

Run: `cd /home/pavel/projects/personal/hestia/backend && make lint`
Expected: No errors

**Step 3: Final commit (if any fixes needed)**

```bash
git add -A
git commit -s -m "fix(tasks): address linter and test issues"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Chore Entity + ScheduleType enum | 2 files |
| 2 | Task Entity + TaskPriority enum | 2 files |
| 3 | Repositories | 2 files |
| 4 | Database Migration | 1 file |
| 5 | Request DTOs | 4 files |
| 6 | Response DTOs | 2 files |
| 7 | Exceptions | 2 files |
| 8 | Services | 2 files |
| 9 | ChoreController | 1 file |
| 10 | TaskController | 1 file |
| 11 | Test Factories | 2 files |
| 12 | ChoreController Tests | 1 file |
| 13 | TaskController Tests | 1 file |
| 14 | Final verification | - |

**Total: ~23 new files, 14 commits**
