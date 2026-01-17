<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Barcode;
use App\Entity\Product;
use App\Repository\BarcodeRepository;
use App\Repository\CategoryRepository;
use App\Repository\LocationRepository;
use App\Repository\ProductRepository;
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
        private readonly BarcodeRepository $barcodeRepository,
        private readonly ValidatorInterface $validator,
    ) {}

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

    /**
     * @param array{name: string, category_id: string, default_location_id: string, default_expiry_days?: int|null, min_stock?: int, active?: bool} $data
     */
    public function createProduct(array $data): Product
    {
        $category = $this->categoryRepository->find(Uuid::fromString($data['category_id']));
        if ($category === null) {
            throw new BadRequestHttpException('Category not found');
        }

        $location = $this->locationRepository->find(Uuid::fromString($data['default_location_id']));
        if ($location === null) {
            throw new BadRequestHttpException('Location not found');
        }

        $product = new Product();
        $product->setName($data['name']);
        $product->setCategory($category);
        $product->setDefaultLocation($location);

        if (isset($data['default_expiry_days'])) {
            $product->setDefaultExpiryDays($data['default_expiry_days']);
        }

        if (isset($data['min_stock'])) {
            $product->setMinStock($data['min_stock']);
        }

        if (isset($data['active'])) {
            $product->setActive($data['active']);
        }

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * @param array{name?: string, category_id?: string, default_location_id?: string, default_expiry_days?: int|null, min_stock?: int, active?: bool} $data
     */
    public function updateProduct(Uuid $id, array $data): Product
    {
        $product = $this->getProduct($id);

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (isset($data['category_id'])) {
            $category = $this->categoryRepository->find(Uuid::fromString($data['category_id']));
            if ($category === null) {
                throw new BadRequestHttpException('Category not found');
            }
            $product->setCategory($category);
        }

        if (isset($data['default_location_id'])) {
            $location = $this->locationRepository->find(Uuid::fromString($data['default_location_id']));
            if ($location === null) {
                throw new BadRequestHttpException('Location not found');
            }
            $product->setDefaultLocation($location);
        }

        if (array_key_exists('default_expiry_days', $data)) {
            $product->setDefaultExpiryDays($data['default_expiry_days']);
        }

        if (isset($data['min_stock'])) {
            $product->setMinStock($data['min_stock']);
        }

        if (isset($data['active'])) {
            $product->setActive($data['active']);
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

        // Check for references (to be extended when stock tracking is added)
        // For now, we can always hard delete

        $this->em->remove($product);
        $this->em->flush();
    }

    /** @return Barcode[] */
    public function listBarcodes(Uuid $productId): array
    {
        // Verify product exists
        $this->getProduct($productId);

        return $this->barcodeRepository->findByProduct($productId);
    }

    public function addBarcode(Uuid $productId, string $code): Barcode
    {
        $product = $this->getProduct($productId);

        // Check if barcode already exists
        $existing = $this->barcodeRepository->findByCode($code);
        if ($existing !== null) {
            throw new BadRequestHttpException('This barcode is already registered');
        }

        $barcode = new Barcode();
        $barcode->setBarcode($code);
        $barcode->setProduct($product);

        $errors = $this->validator->validate($barcode);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $this->em->persist($barcode);
        $this->em->flush();

        return $barcode;
    }

    public function removeBarcode(Uuid $productId, string $code): void
    {
        $barcode = $this->barcodeRepository->findOneByProductAndCode($productId, $code);

        if ($barcode === null) {
            throw new NotFoundHttpException('Barcode not found');
        }

        $this->em->remove($barcode);
        $this->em->flush();
    }

    public function lookupBarcode(string $code): Product
    {
        $barcode = $this->barcodeRepository->findByCode($code);

        if ($barcode === null) {
            throw new NotFoundHttpException('Barcode not found');
        }

        return $barcode->getProduct();
    }
}
