<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Service;

use App\Entity\Category;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use App\Service\CategoryService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CategoryServiceTest extends TestCase
{
    public function testCreatePersistsAndReturnsCategory(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = new CategoryService(
            $em,
            $this->createStub(CategoryRepository::class),
            $this->createStub(ProductRepository::class)
        );

        $result = $service->create(new CreateCategoryRequest('Снеки'));
        static::assertSame('Снеки', $result->getName());
    }

    public function testCreateTranslatesUniqueViolationToNameTaken(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new CategoryService(
            $em,
            $this->createStub(CategoryRepository::class),
            $this->createStub(ProductRepository::class)
        );

        $this->expectException(CategoryNameTakenException::class);
        $service->create(new CreateCategoryRequest('Снеки'));
    }

    public function testUpdateTranslatesUniqueViolationToNameTaken(): void
    {
        $existing = new Category();
        $existing->setName('Напитки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $service = new CategoryService($em, $repo, $this->createStub(ProductRepository::class));

        $this->expectException(CategoryNameTakenException::class);
        $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
    }

    public function testUpdateChangesNameAndFlushes(): void
    {
        $existing = new Category();
        $existing->setName('Напитки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new CategoryService($em, $repo, $this->createStub(ProductRepository::class));

        $result = $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
        static::assertSame('Снеки', $result->getName());
    }

    public function testUpdateWithSameNameDoesNotFlush(): void
    {
        $existing = new Category();
        $existing->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = new CategoryService($em, $repo, $this->createStub(ProductRepository::class));

        $result = $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
        static::assertSame('Снеки', $result->getName());
    }

    public function testUpdateMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new CategoryService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class)
        );

        $this->expectException(CategoryNotFoundException::class);
        $service->update(Uuid::v7(), new UpdateCategoryRequest('Снеки'));
    }

    public function testDeleteInUseThrowsConflict(): void
    {
        $category = new Category();
        $category->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($category);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(3);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $service = new CategoryService($em, $repo, $products);

        $this->expectException(CategoryInUseException::class);
        $service->delete(Uuid::v7());
    }

    public function testDeleteEmptyRemovesAndFlushes(): void
    {
        $category = new Category();
        $category->setName('Снеки');

        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn($category);

        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($category);
        $em->expects($this->once())->method('flush');

        $service = new CategoryService($em, $repo, $products);
        $service->delete(Uuid::v7());
    }

    public function testDeleteMissingThrowsNotFound(): void
    {
        $repo = $this->createStub(CategoryRepository::class);
        $repo->method('find')->willReturn(null);

        $service = new CategoryService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(ProductRepository::class)
        );

        $this->expectException(CategoryNotFoundException::class);
        $service->delete(Uuid::v7());
    }

    public function testUsageCountCountsProducts(): void
    {
        $products = $this->createStub(ProductRepository::class);
        $products->method('count')->willReturn(5);

        $service = new CategoryService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CategoryRepository::class),
            $products
        );

        static::assertSame(5, $service->usageCount(new Category()));
    }
}
