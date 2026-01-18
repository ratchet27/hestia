<?php

declare(strict_types = 1);

namespace App\Exception;

use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 7807 Problem Details value object.
 */
final readonly class ApiProblem implements JsonSerializable
{
    /**
     * @param array<string, mixed> $extraData
     */
    public function __construct(
        public string $title,
        public string $type,
        public int $code = Response::HTTP_BAD_REQUEST,
        public array $extraData = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'title' => $this->title,
            'type' => $this->type,
            'code' => $this->code,
            ...$this->extraData
        ];
    }
}
