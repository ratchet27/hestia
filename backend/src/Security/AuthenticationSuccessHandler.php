<?php

declare(strict_types = 1);

namespace App\Security;

use App\Entity\User;
use App\Response\Auth\UserResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private ObjectMapperInterface $mapper,
        private SerializerInterface $serializer
    ) {
    }

    // @mago-ignore lint:sensitive-parameter
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $payload = $this->serializer->serialize(['data' => $this->mapper->map($user, UserResponse::class)], 'json');

        return new JsonResponse($payload, Response::HTTP_OK, [], true);
    }
}
