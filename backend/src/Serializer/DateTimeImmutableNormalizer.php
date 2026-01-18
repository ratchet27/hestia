<?php

declare(strict_types = 1);

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class DateTimeImmutableNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        /** @var \DateTimeImmutable $data */
        return $data->format(\DateTimeInterface::ATOM);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \DateTimeImmutable;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [\DateTimeImmutable::class => true];
    }
}
