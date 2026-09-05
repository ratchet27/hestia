<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Chore;
use App\Enum\ScheduleType;
use App\Exception\Chore\ChoreNotFoundException;
use App\Repository\ChoreRepository;
use App\Request\SaveChoreRequest;
use App\Service\Time\HouseholdCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ChoreService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChoreRepository $choreRepository,
        private readonly HouseholdCalendar $calendar
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

    public function createChore(SaveChoreRequest $request): Chore
    {
        $chore = new Chore();
        $chore->setName($request->name);
        $chore->setScheduleType(ScheduleType::from($request->schedule_type));
        $chore->setScheduleValue($request->schedule_value);
        $chore->setAssignee($request->assignee);
        $chore->initializeNextDueAt($this->calendar->now());

        $this->em->persist($chore);
        $this->em->flush();

        return $chore;
    }

    public function updateChore(Uuid $id, SaveChoreRequest $request): Chore
    {
        $chore = $this->getChore($id);
        $chore->setName($request->name);
        $chore->setAssignee($request->assignee);

        $newType = ScheduleType::from($request->schedule_type);
        $scheduleChanged =
            $newType !== $chore->getScheduleType() || $request->schedule_value !== $chore->getScheduleValue();

        // Recompute next-due only on a real schedule change; a name/assignee-only edit
        // must leave nextDueAt untouched. Anchored to now (clock restart) via reschedule.
        if ($scheduleChanged) {
            $chore->reschedule($newType, $request->schedule_value, $this->calendar->now());
        }

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
        $chore->markDone($this->calendar->now());

        $this->em->flush();

        return $chore;
    }
}
