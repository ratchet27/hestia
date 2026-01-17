<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal\V1;

use App\DTO\Request\CreateBarcodeRequest;
use App\DTO\Response\ProductResponse;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class BarcodeController extends AbstractController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    #[Route('/products/{id}/barcodes', name: 'api_products_barcodes_list', methods: ['GET'])]
    public function listForProduct(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $barcodes = $this->productService->listBarcodes($uuid);

        $data = array_map(
            static fn($barcode) => [
                'id' => (string) $barcode->getId(),
                'barcode' => $barcode->getBarcode(),
                'product_id' => (string) $barcode->getProduct()->getId(),
                'created_at' => $barcode->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
            $barcodes
        );

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)],
        ]);
    }

    #[Route('/products/{id}/barcodes', name: 'api_products_barcodes_create', methods: ['POST'])]
    public function addToProduct(
        string $id,
        #[MapRequestPayload] CreateBarcodeRequest $request,
    ): JsonResponse {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $barcode = $this->productService->addBarcode($uuid, $request->barcode);

        return $this->json(
            [
                'data' => [
                    'id' => (string) $barcode->getId(),
                    'barcode' => $barcode->getBarcode(),
                    'product_id' => (string) $barcode->getProduct()->getId(),
                    'created_at' => $barcode->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/products/{id}/barcodes/{barcode}', name: 'api_products_barcodes_delete', methods: ['DELETE'])]
    public function removeFromProduct(string $id, string $barcode): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('Invalid UUID format');
        }

        $this->productService->removeBarcode($uuid, $barcode);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/barcodes/{code}', name: 'api_barcodes_lookup', methods: ['GET'])]
    public function lookup(string $code): JsonResponse
    {
        $product = $this->productService->lookupBarcode($code);

        return $this->json([
            'data' => ProductResponse::fromEntity($product, includeBarcodes: true)->toArray(),
        ]);
    }
}
