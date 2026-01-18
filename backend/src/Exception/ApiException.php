<?php

declare(strict_types = 1);

namespace App\Exception;

use RuntimeException;
use Throwable;

/**
 * Base exception for all API errors.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ApiProblem $problem,
        ?Throwable $previous = null
    ) {
        parent::__construct($problem->title, $problem->code, $previous);
    }
}
