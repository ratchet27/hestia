<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Exception\Chore\ChoreNotFoundException;
use App\Repository\ChoreRepository;
use App\Request\CreateChoreRequest;
use App\Request\UpdateChoreRequest;
use App\Service\Time\AppTimezone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

class ChoreService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChoreRepository $choreRepository,
        private readonly ClockInterface $clock,
        private readonly AppTimezone $appTimezone
    ) {
    }

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
        $chore->initializeNextDueAt($this->now());

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
        $chore->markDone($this->now());

        $this->em->flush();

        return $chore;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->appTimezone->get());
    }
}
