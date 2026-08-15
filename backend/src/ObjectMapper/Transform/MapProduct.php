<?php

declare(strict_types = 1);

namespace App\ObjectMapper\Transform;

use App\Response\Product\ProductResponse;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * Transform that maps a Product entity to ProductResponse.
 *
 * @implements TransformCallableInterface<object, ProductResponse>
 */
// @mago-expect analysis:excess-template-parameter
class MapProduct implements TransformCallableInterface
{
    public function __construct(
        private readonly ObjectMapperInterface $objectMapper = new ObjectMapper()
    ) {
    }

    // @mago-expect analysis:docblock-type-mismatch
    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (!is_object($value)) {
            return null;
        }

        return $this->objectMapper->map($value, ProductResponse::class);
    }
}
