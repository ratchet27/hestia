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
        public array $extraData = [],
        /** Human-readable specifics for this occurrence (RFC 7807 `detail`); title stays generic. */
        public ?string $detail = null
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
            ...( $this->detail !== null ? ['detail' => $this->detail] : [] ),
            ...$this->extraData
        ];
    }
}
