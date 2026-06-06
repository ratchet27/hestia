<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Location;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class LocationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocationRepository $locationRepository,
        private ProductRepository $productRepository,
        private StockEntryRepository $stockEntryRepository
    ) {
    }

    /** @return Location[] */
    public function list(): array
    {
        return $this->locationRepository->findAllOrderedByName();
    }

    public function create(CreateLocationRequest $request): Location
    {
        $location = new Location();
        $location->setName($request->name);

        $this->em->persist($location);
        $this->flushOrNameTaken($request->name);

        return $location;
    }

    public function update(Uuid $id, UpdateLocationRequest $request): Location
    {
        $location = $this->locationRepository->find($id) ?? throw new LocationNotFoundException($id);

        if ($request->name !== $location->getName()) {
            $location->setName($request->name);
            $this->flushOrNameTaken($request->name);
        }

        return $location;
    }

    public function delete(Uuid $id): void
    {
        $location = $this->locationRepository->find($id) ?? throw new LocationNotFoundException($id);

        $usage = $this->usageCount($location);
        if ($usage > 0) {
            throw new LocationInUseException($usage);
        }

        $this->em->remove($location);
        $this->em->flush();
    }

    public function usageCount(Location $location): int
    {
        return (
            $this->productRepository->count(['defaultLocation' => $location])
            + $this->stockEntryRepository->count(['location' => $location])
        );
    }

    /**
     * The DB unique constraint is the single authority for name uniqueness;
     * translate its violation (incl. the concurrent-create race) into a clean 409.
     */
    private function flushOrNameTaken(string $name): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new LocationNameTakenException($name);
        }
    }
}
