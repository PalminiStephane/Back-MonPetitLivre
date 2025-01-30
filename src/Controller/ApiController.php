<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="api_")
 */
class ApiController extends AbstractController
{
    /**
     * @Route("/test", name="test", methods={"GET"})
     */
    public function test(): JsonResponse
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            return $this->json([
                'status' => 'success',
                'message' => 'API test works!',
                'data' => [
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}