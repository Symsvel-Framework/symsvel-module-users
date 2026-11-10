<?php

declare(strict_types=1);

namespace SymSvel\Module\Users\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UsersPingController
{
    #[Route('/api/users/ping', name: 'symsvel_users_ping', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['module' => 'users', 'status' => 'ok']);
    }
}
