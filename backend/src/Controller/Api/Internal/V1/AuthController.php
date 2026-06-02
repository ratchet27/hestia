<?php

declare(strict_types = 1);

namespace App\Controller\Api\Internal\V1;

use App\Entity\User;
use App\Response\Auth\UserResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ObjectMapperInterface $mapper
    ) {
    }

    #[Route('/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(description: 'Authenticate with username and password.', summary: 'Log in')]
    #[OA\RequestBody(content: new OA\JsonContent(properties: [
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'password', type: 'string')
    ]))]
    #[OA\Response(response: 200, description: 'Logged in', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: UserResponse::class))
    ]))]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    public function login(): never
    {
        // Intercepted by the json_login authenticator; never executed.
        throw new \LogicException('Handled by the security firewall.');
    }

    #[Route('/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    #[OA\Post(description: 'Log out and clear the session.', summary: 'Log out')]
    #[OA\Response(response: 204, description: 'Logged out')]
    public function logout(): never
    {
        // Intercepted by the logout listener; never executed.
        throw new \LogicException('Handled by the security firewall.');
    }

    #[Route('/auth/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Get(description: 'Return the currently authenticated user.', summary: 'Current user')]
    #[OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'data', ref: new Model(type: UserResponse::class))
    ]))]
    #[OA\Response(response: 401, description: 'Not authenticated')]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['data' => $this->mapper->map($user, UserResponse::class)]);
    }

    #[Route('/auth/csrf', name: 'api_auth_csrf', methods: ['GET'])]
    #[OA\Get(description: 'Issue a CSRF double-submit cookie.', summary: 'CSRF token')]
    #[OA\Response(response: 204, description: 'CSRF cookie set')]
    public function csrf(): Response
    {
        // The CsrfDoubleSubmitSubscriber (added in a later task) sets the cookie on the response; this just provides a cheap endpoint to hit.
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
