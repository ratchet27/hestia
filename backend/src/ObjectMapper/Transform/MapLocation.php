<?php

declare(strict_types = 1);

namespace App\ObjectMapper\Transform;

use App\Response\Location\LocationResponse;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * Transform that maps a Location entity to LocationResponse.
 *
 * @implements TransformCallableInterface<object, LocationResponse>
 */
class MapLocation implements TransformCallableInterface
{
    public function __construct(
        private readonly ObjectMapperInterface $objectMapper = new ObjectMapper()
    ) {
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (!is_object($value)) {
            return null;
        }

        return $this->objectMapper->map($value, LocationResponse::class);
    }
}
