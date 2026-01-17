<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Barcode;
use App\Entity\Product;
use App\Repository\BarcodeRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BarcodeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        private readonly BarcodeRepository $barcodeRepository,
        private readonly ValidatorInterface $validator,
    ) {}

    /** @return Barcode[] */
    public function listBarcodes(Uuid $productId): array
    {
        $this->getProduct($productId);

        return $this->barcodeRepository->findByProduct($productId);
    }

    public function addBarcode(Uuid $productId, string $code): Barcode
    {
        $product = $this->getProduct($productId);

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

    private function getProduct(Uuid $id): Product
    {
        $product = $this->productRepository->find($id);

        if ($product === null) {
            throw new NotFoundHttpException('Product not found');
        }

        return $product;
    }
}
