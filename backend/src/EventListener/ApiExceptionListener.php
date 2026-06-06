<?php

declare(strict_types = 1);

namespace App\EventListener;

use App\Exception\ApiException;
use App\Exception\ApiProblem;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $problem = $this->createProblem($exception);

        if ($problem->code >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->critical('API error: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod()
            ]);
        }

        $response = new JsonResponse(data: $problem, status: $problem->code, headers: [
            'Content-Type' => 'application/problem+json'
        ]);

        $event->setResponse($response);
    }

    private function createProblem(\Throwable $exception): ApiProblem
    {
        if ($exception instanceof ApiException) {
            return $exception->problem;
        }

        if ($exception instanceof ValidationFailedException) {
            return $this->createValidationProblem($exception);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $previous = $exception->getPrevious();

            // #[MapRequestPayload] wraps DTO validation failures in a 422 HttpException;
            // unwrap so they surface as VALIDATION_ERROR. Note: createValidationProblem
            // hardcodes 422, which matches MapRequestPayload but would override the 400
            // that #[MapQueryString] uses by default — revisit if a query-string DTO is added.
            if ($previous instanceof ValidationFailedException) {
                return $this->createValidationProblem($previous);
            }

            return $this->createHttpProblem($exception);
        }

        return new ApiProblem(
            title: 'Internal Server Error',
            type: 'INTERNAL_SERVER_ERROR',
            code: Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    private function createValidationProblem(ValidationFailedException $exception): ApiProblem
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = [
                'property' => $violation->getPropertyPath(),
                'violation' => $violation->getMessage()
            ];
        }

        return new ApiProblem(
            title: 'Validation failed',
            type: 'VALIDATION_ERROR',
            code: Response::HTTP_UNPROCESSABLE_ENTITY,
            extraData: ['errors' => $errors]
        );
    }

    private function createHttpProblem(HttpExceptionInterface $exception): ApiProblem
    {
        $statusCode = $exception->getStatusCode();
        $type = match ($statusCode) {
            Response::HTTP_BAD_REQUEST => 'BAD_REQUEST',
            Response::HTTP_UNAUTHORIZED => 'UNAUTHORIZED',
            Response::HTTP_FORBIDDEN => 'FORBIDDEN',
            Response::HTTP_NOT_ACCEPTABLE => 'NOT_ACCEPTABLE',
            Response::HTTP_NOT_FOUND => 'NOT_FOUND',
            Response::HTTP_METHOD_NOT_ALLOWED => 'METHOD_NOT_ALLOWED',
            Response::HTTP_CONFLICT => 'CONFLICT',
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'UNSUPPORTED_MEDIA_TYPE',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'UNPROCESSABLE_ENTITY',
            default => 'HTTP_ERROR'
        };

        $message = $exception->getMessage();
        $title = $message !== '' ? $message : Response::$statusTexts[$statusCode] ?? 'Error';

        return new ApiProblem(title: $title, type: $type, code: $statusCode);
    }
}
