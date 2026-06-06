<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Category;
use App\Exception\Category\CategoryInUseException;
use App\Exception\Category\CategoryNameTakenException;
use App\Exception\Category\CategoryNotFoundException;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Request\CreateCategoryRequest;
use App\Request\UpdateCategoryRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class CategoryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private ProductRepository $productRepository
    ) {
    }

    /** @return Category[] */
    public function list(): array
    {
        return $this->categoryRepository->findAllOrderedByName();
    }

    public function create(CreateCategoryRequest $request): Category
    {
        $category = new Category();
        $category->setName($request->name);

        $this->em->persist($category);
        $this->flushOrNameTaken($request->name);

        return $category;
    }

    public function update(Uuid $id, UpdateCategoryRequest $request): Category
    {
        $category = $this->categoryRepository->find($id) ?? throw new CategoryNotFoundException($id);

        if ($request->name !== $category->getName()) {
            $category->setName($request->name);
            $this->flushOrNameTaken($request->name);
        }

        return $category;
    }

    public function delete(Uuid $id): void
    {
        $category = $this->categoryRepository->find($id) ?? throw new CategoryNotFoundException($id);

        $usage = $this->usageCount($category);
        if ($usage > 0) {
            throw new CategoryInUseException($usage);
        }

        $this->em->remove($category);
        $this->em->flush();
    }

    public function usageCount(Category $category): int
    {
        return $this->productRepository->count(['category' => $category]);
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
            throw new CategoryNameTakenException($name);
        }
    }
}
