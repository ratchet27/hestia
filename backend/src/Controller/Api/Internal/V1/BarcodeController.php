<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Exception\ApiProblem;
use App\Request\CreateBarcodeRequest;
use App\Response\Barcode\BarcodeResponse;
use App\Response\Product\ProductResponse;
use App\Service\BarcodeService;
use Nelmio\ApiDocBundle\Attribute\Model;
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
    #[OA\Get(description: 'Returns all barcodes associated with a product.', summary: 'List product barcodes')]
    #[OA\Response(
        response: 200,
        description: 'List of barcodes for a product',
        content: new OA\JsonContent(properties: [
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(ref: new Model(type: BarcodeResponse::class))
            ),
            new OA\Property(
                property: 'meta',
                properties: [new OA\Property(property: 'total', type: 'integer')],
                type: 'object'
            )
        ])
    )]
    #[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
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
    #[OA\Post(description: 'Associates a new barcode with a product.', summary: 'Add barcode to product')]
    #[OA\Response(response: 201, description: 'Barcode added to product', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: BarcodeResponse::class))
    ]))]
    #[OA\Response(
        response: 400,
        description: 'Invalid input or barcode already exists',
        content: new Model(type: ApiProblem::class)
    )]
    #[OA\Response(response: 404, description: 'Product not found', content: new Model(type: ApiProblem::class))]
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

    #[Route('/barcodes/{code}', name: 'api_barcodes_delete', methods: ['DELETE'])]
    #[OA\Delete(description: 'Removes a barcode from the system.', summary: 'Delete barcode')]
    #[OA\Response(response: 204, description: 'Barcode deleted')]
    #[OA\Response(response: 404, description: 'Barcode not found', content: new Model(type: ApiProblem::class))]
    public function delete(string $code): JsonResponse
    {
        $this->barcodeService->removeBarcode($code);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/barcodes/{code}', name: 'api_barcodes_lookup', methods: ['GET'])]
    #[OA\Get(description: 'Finds the product associated with a barcode.', summary: 'Lookup barcode')]
    #[OA\Response(response: 200, description: 'Product found by barcode', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: ProductResponse::class))
    ]))]
    #[OA\Response(response: 404, description: 'Barcode not found', content: new Model(type: ApiProblem::class))]
    public function lookup(string $code): JsonResponse
    {
        $product = $this->barcodeService->lookupBarcode($code);

        return $this->json([
            'data' => $this->mapper->map($product, ProductResponse::class)
        ]);
    }
}
