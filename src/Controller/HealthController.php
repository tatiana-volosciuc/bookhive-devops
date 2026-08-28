<?php


namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
