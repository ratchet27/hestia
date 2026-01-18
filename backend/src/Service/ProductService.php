<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Request\CreateProductRequest;
use App\Request\UpdateProductRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LocationRepository $locationRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * @param array{name?: string, category_id?: string, active?: bool} $filters
     * @return Product[]
     */
    public function listProducts(array $filters): array
    {
        return $this->productRepository->findByFilters($filters);
    }

    public function getProduct(Uuid $id): Product
    {
        $product = $this->productRepository->findOneWithBarcodes($id);

        if ($product === null) {
            throw new NotFoundHttpException('Product not found');
        }

        return $product;
    }

    public function createProduct(CreateProductRequest $request): Product
    {
        $category = $this->categoryRepository->find(Uuid::fromString($request->categoryId));
        if ($category === null) {
            throw new BadRequestHttpException('Category not found');
        }

        $location = $this->locationRepository->find(Uuid::fromString($request->defaultLocationId));
        if ($location === null) {
            throw new BadRequestHttpException('Location not found');
        }

        $product = new Product();
        $product->setName($request->name);
        $product->setCategory($category);
        $product->setDefaultLocation($location);
        $product->setDefaultExpiryDays($request->defaultExpiryDays);
        $product->setMinStock($request->minStock);
        $product->setActive($request->active);

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function updateProduct(Uuid $id, UpdateProductRequest $request): Product
    {
        $product = $this->getProduct($id);

        if ($request->name !== null) {
            $product->setName($request->name);
        }

        if ($request->categoryId !== null) {
            $category = $this->categoryRepository->find(Uuid::fromString($request->categoryId));
            if ($category === null) {
                throw new BadRequestHttpException('Category not found');
            }

            $product->setCategory($category);
        }

        if ($request->defaultLocationId !== null) {
            $location = $this->locationRepository->find(Uuid::fromString($request->defaultLocationId));
            if ($location === null) {
                throw new BadRequestHttpException('Location not found');
            }

            $product->setDefaultLocation($location);
        }

        if ($request->defaultExpiryDays !== null) {
            $product->setDefaultExpiryDays($request->defaultExpiryDays);
        } elseif ($request->clearDefaultExpiryDays) {
            $product->setDefaultExpiryDays(null);
        }

        if ($request->minStock !== null) {
            $product->setMinStock($request->minStock);
        }

        if ($request->active !== null) {
            $product->setActive($request->active);
        }

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $this->em->flush();

        return $product;
    }

    public function softDelete(Uuid $id): void
    {
        $product = $this->getProduct($id);
        $product->setActive(false);

        $this->em->flush();
    }

    public function hardDelete(Uuid $id): void
    {
        $product = $this->getProduct($id);

        $this->em->remove($product);
        $this->em->flush();
    }
}
