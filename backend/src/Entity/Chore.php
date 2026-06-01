<?php

declare(strict_types = 1);

namespace App\Entity;

use App\Enum\ScheduleType;
use App\Repository\ChoreRepository;
use App\Response\Chore\ChoreResponse;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChoreRepository::class)]
#[ORM\Table(name: 'chores')]
#[ORM\HasLifecycleCallbacks]
#[Map(target: ChoreResponse::class)]
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

    public function initializeNextDueAt(\DateTimeImmutable $now): static
    {
        $this->nextDueAt = $this->calculateNextDueAt($now);
        return $this;
    }

    public function markDone(\DateTimeImmutable $now): static
    {
        $this->lastDoneAt = $now;
        $this->nextDueAt = $this->calculateNextDueAt($now);
        return $this;
    }

    private function calculateNextDueAt(\DateTimeImmutable $from): \DateTimeImmutable
    {
        $date = $from->setTime(0, 0);

        $result = match ($this->scheduleType) {
            ScheduleType::INTERVAL => $date->modify(sprintf('+%d days', $this->scheduleValue)),
            ScheduleType::FIXED_WEEKLY => $this->nextWeekday($date, $this->scheduleValue),
            ScheduleType::FIXED_MONTHLY => $this->nextMonthDay($date, $this->scheduleValue)
        };

        return $result->setTime(0, 0);
    }

    private function nextWeekday(\DateTimeImmutable $from, int $targetWeekday): \DateTimeImmutable
    {
        $currentWeekday = (int) $from->format('N');
        $daysUntil = $targetWeekday - $currentWeekday;
        if ($daysUntil <= 0) {
            $daysUntil += 7;
        }

        return $from->modify(sprintf('+%d days', $daysUntil));
    }

    private function nextMonthDay(\DateTimeImmutable $from, int $targetDay): \DateTimeImmutable
    {
        $currentDay = (int) $from->format('j');
        $anchor = $currentDay < $targetDay ? $from : $from->modify('first day of next month');

        $lastDay = (int) $anchor->format('t');
        $day = min($targetDay, $lastDay);

        // If clamping reproduced $from (month too short to reach the target day),
        // advance one more month and clamp again so the chore never stays on today.
        if ($anchor === $from && $day === $currentDay) {
            $anchor = $from->modify('first day of next month');
            $lastDay = (int) $anchor->format('t');
            $day = min($targetDay, $lastDay);
        }

        return $anchor->setDate((int) $anchor->format('Y'), (int) $anchor->format('m'), $day);
    }
}
