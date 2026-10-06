<?php

namespace App\Controller;

use App\Dto\RegisterUserData;
use App\Exception\UsernameAlreadyTakenException;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('POST /api/login é atendido pelo json_login.');
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, RegistrationService $registration): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'JSON inválido.'], 400);
        }

        try {
            $user = $registration->register(new RegisterUserData(
                (string) ($payload['username'] ?? ''),
                (string) ($payload['password'] ?? ''),
            ));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (UsernameAlreadyTakenException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        return new JsonResponse(['status' => 'User created', 'id' => $user->getId()], 201);
    }
}
