<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Request\CreateBarcodeRequest;
use App\Response\Barcode\BarcodeResponse;
use App\Response\Product\ProductResponse;
use App\Service\BarcodeService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Barcodes')]
final class BarcodeController extends AbstractController
{
    public function __construct(
        private readonly BarcodeService $barcodeService,
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route(
        '/products/{uuid}/barcodes',
        name: 'api_products_barcodes_list',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['GET']
    )]
    #[OA\Response(response: 200, description: 'List of barcodes for a product')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function listForProduct(Uuid $uuid): JsonResponse
    {
        $barcodes = $this->barcodeService->listBarcodes($uuid);

        $data = array_map(fn($barcode) => $this->mapper->map($barcode, BarcodeResponse::class), $barcodes);

        return $this->json([
            'data' => $data,
            'meta' => ['total' => count($data)]
        ]);
    }

    #[Route(
        '/products/{uuid}/barcodes',
        name: 'api_products_barcodes_create',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['POST']
    )]
    #[OA\Response(response: 201, description: 'Barcode added to product')]
    #[OA\Response(response: 400, description: 'Invalid input or barcode already exists')]
    #[OA\Response(response: 404, description: 'Product not found')]
    public function addToProduct(
        Uuid $uuid,
        #[MapRequestPayload]
        CreateBarcodeRequest $request
    ): JsonResponse {
        $barcode = $this->barcodeService->addBarcode($uuid, $request->barcode);

        return $this->json([
            'data' => $this->mapper->map($barcode, BarcodeResponse::class)
        ], Response::HTTP_CREATED);
    }

    #[Route(
        '/products/{uuid}/barcodes/{barcode}',
        name: 'api_products_barcodes_delete',
        requirements: ['uuid' => Requirement::UUID_V7],
        methods: ['DELETE']
    )]
    #[OA\Response(response: 204, description: 'Barcode removed from product')]
    #[OA\Response(response: 404, description: 'Product or barcode not found')]
    public function removeFromProduct(Uuid $uuid, string $barcode): JsonResponse
    {
        $this->barcodeService->removeBarcode($uuid, $barcode);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/barcodes/{code}', name: 'api_barcodes_lookup', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Product found by barcode')]
    #[OA\Response(response: 404, description: 'Barcode not found')]
    public function lookup(string $code): JsonResponse
    {
        $product = $this->barcodeService->lookupBarcode($code);

        return $this->json([
            'data' => $this->mapper->map($product, ProductResponse::class)
        ]);
    }
}
