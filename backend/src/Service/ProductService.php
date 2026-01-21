<?php

declare(strict_types = 1);

namespace App\Service;

use App\Entity\Barcode;
use App\Entity\Product;
use App\Exception\Barcode\BarcodeAlreadyExistsException;
use App\Exception\Product\CategoryNotFoundException;
use App\Exception\Product\LocationNotFoundException;
use App\Exception\Product\ProductNotFoundException;
use App\Repository\BarcodeRepository;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
use App\Repository\StockEntryRepository;
use App\Request\CreateProductRequest;
use App\Request\UpdateProductRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// @mago-ignore lint:cyclomatic-complexity
// @mago-ignore lint:kan-defect
class ProductService
{
    // @mago-ignore lint:excessive-parameter-list
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LocationRepository $locationRepository,
        private readonly StockEntryRepository $stockEntryRepository,
        private readonly BarcodeRepository $barcodeRepository,
        private readonly ShoppingListService $shoppingListService,
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
            throw new ProductNotFoundException($id);
        }

        return $product;
    }

    public function createProduct(CreateProductRequest $request): Product
    {
        $categoryId = Uuid::fromString($request->categoryId);
        $category = $this->categoryRepository->find($categoryId);
        if ($category === null) {
            throw new CategoryNotFoundException($categoryId);
        }

        $locationId = Uuid::fromString($request->defaultLocationId);
        $location = $this->locationRepository->find($locationId);
        if ($location === null) {
            throw new LocationNotFoundException($locationId);
        }

        $product = new Product();
        $product->setName($request->name);
        $product->setCategory($category);
        $product->setDefaultLocation($location);
        $product->setDefaultExpiryDays($request->defaultExpiryDays);
        $product->setMinStock($request->minStock);
        $product->setUnit($request->unit);

        $request->active ? $product->activate() : $product->deactivate();

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new ValidationFailedException($product, $errors);
        }

        $this->em->persist($product);

        if ($request->barcodes !== null) {
            $this->syncBarcodes($product, $request->barcodes);
        }

        $this->em->flush();

        return $product;
    }

    public function updateProduct(Uuid $id, UpdateProductRequest $request): Product
    {
        $product = $this->getProduct($id);
        $oldMinStock = $product->getMinStock();

        $categoryId = Uuid::fromString($request->categoryId);
        $category = $this->categoryRepository->find($categoryId);
        if ($category === null) {
            throw new CategoryNotFoundException($categoryId);
        }

        $locationId = Uuid::fromString($request->defaultLocationId);
        $location = $this->locationRepository->find($locationId);
        if ($location === null) {
            throw new LocationNotFoundException($locationId);
        }

        $product->setName($request->name);
        $product->setCategory($category);
        $product->setDefaultLocation($location);
        $product->setDefaultExpiryDays($request->defaultExpiryDays);
        $product->setMinStock($request->minStock);

        if ($request->unit !== null) {
            $product->setUnit($request->unit);
        }

        $request->active ? $product->activate() : $product->deactivate();

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new ValidationFailedException($product, $errors);
        }

        if ($request->barcodes !== null) {
            $this->syncBarcodes($product, $request->barcodes);
        }

        $this->em->flush();

        // Recalculate shopping list if minStock changed
        if ($request->minStock !== $oldMinStock) {
            $currentStock = $this->stockEntryRepository->countByProduct($id);
            $this->shoppingListService->handleStockChange($id, $currentStock, $currentStock);
        }

        return $product;
    }

    public function softDelete(Uuid $id): void
    {
        $product = $this->getProduct($id);
        $product->deactivate();

        $this->em->flush();
    }

    public function hardDelete(Uuid $id): void
    {
        $product = $this->getProduct($id);

        $this->em->remove($product);
        $this->em->flush();
    }

    /** @param string[] $newBarcodes */
    private function syncBarcodes(Product $product, array $newBarcodes): void
    {
        $existingBarcodes = $product->getBarcodes();
        $existingCodes = [];

        foreach ($existingBarcodes as $barcode) {
            $existingCodes[$barcode->getBarcode()] = $barcode;
        }

        // Remove barcodes not in the new array
        foreach ($existingCodes as $code => $barcode) {
            if (in_array($code, $newBarcodes, true)) {
                continue;
            }

            $product->removeBarcode($barcode);
            $this->em->remove($barcode);
        }

        // Add new barcodes
        foreach ($newBarcodes as $code) {
            if (isset($existingCodes[$code])) {
                continue;
            }

            // Check if barcode exists for another product
            $existing = $this->barcodeRepository->findByCode($code);
            if ($existing !== null && $existing->getProduct()->getId() !== $product->getId()) {
                throw new BarcodeAlreadyExistsException($code, $existing->getProduct()->getName());
            }

            $barcode = new Barcode();
            $barcode->setBarcode($code);
            $product->addBarcode($barcode);
        }
    }
}
