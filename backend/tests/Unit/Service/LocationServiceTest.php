<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Location;
use App\Exception\Location\LocationInUseException;
use App\Exception\Location\LocationNameTakenException;
use App\Exception\Location\LocationNotFoundException;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\CreateLocationRequest;
use App\Request\UpdateLocationRequest;
use App\Service\LocationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class LocationServiceTest extends TestCase
{
    public function testCreateTranslatesUniqueViolationToNameTaken(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new LocationService(
            $em,
            $this->createStub(LocationRepository::class),
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNameTakenException::class);
        $service->create(new CreateLocationRequest('Кладовка'));
    }

    public function testCreatePersistsAndReturnsLocation(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new LocationService(
            $em,
            $this->createStub(LocationRepository::class),
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $result = $service->create(new CreateLocationRequest('Кладовка'));
        static::assertSame('Кладовка', $result->getName());
    }

    public function testUpdateChangesNameAndFlushes(): void
    {
        $existing = new Location();
        $existing->setName('Балкон');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new LocationService(
            $em,
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $result = $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
        static::assertSame('Кладовка', $result->getName());
    }

    public function testUpdateWithSameNameDoesNotFlush(): void
    {
        $existing = new Location();
        $existing->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = new LocationService(
            $em,
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $result = $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
        static::assertSame('Кладовка', $result->getName());
    }

    public function testUpdateMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new LocationService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNotFoundException::class);
        $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
    }

    public function testUpdateTranslatesUniqueViolationToNameTaken(): void
    {
        $existing = new Location();
        $existing->setName('Балкон');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new LocationService(
            $em,
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNameTakenException::class);
        $service->update(Uuid::v7(), new UpdateLocationRequest('Кладовка'));
    }

    public function testDeleteInUseThrowsConflict(): void
    {
        $location = new Location();
        $location->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(1);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $service = new LocationService($em, $repo, $products, $stock);

        $this->expectException(LocationInUseException::class);
        $service->delete(Uuid::v7());
    }

    public function testDeleteBlockedByStockEntriesAlone(): void
    {
        $location = new Location();
        $location->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(0);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $service = new LocationService($em, $repo, $products, $stock);

        $this->expectException(LocationInUseException::class);
        $service->delete(Uuid::v7());
    }

    public function testDeleteEmptyRemovesAndFlushes(): void
    {
        $location = new Location();
        $location->setName('Кладовка');

        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn($location);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(0);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($location);
        $em->expects($this->once())->method('flush');

        $service = new LocationService($em, $repo, $products, $stock);
        $service->delete(Uuid::v7());
    }

    public function testDeleteMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(LocationRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new LocationService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class),
            $this->createStub(StockEntryRepository::class)
        );

        $this->expectException(LocationNotFoundException::class);
        $service->delete(Uuid::v7());
    }

    public function testUsageCountSumsProductsAndStockEntries(): void
    {
        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(2);
        $stock = $this->createStub(StockEntryRepository::class);
        $stock->method('count')->willReturn(5);

        $service = new LocationService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(LocationRepository::class),
            $products,
            $stock
        );

        static::assertSame(7, $service->usageCount(new Location()));
    }
}
