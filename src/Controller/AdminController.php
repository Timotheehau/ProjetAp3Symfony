<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/admin')]
#[OA\Tag(name: 'Admin')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Liste tous les utilisateurs (Admin uniquement)',
        security: [['Bearer' => []]],
        tags: ['Admin']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des utilisateurs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Accès refusé - ROLE_ADMIN requis')]
    public function users(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();
        
        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'userType' => $user->getUserType(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $users);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/stats',
        summary: 'Statistiques de la plateforme (Admin uniquement)',
        security: [['Bearer' => []]],
        tags: ['Admin']
    )]
    #[OA\Response(
        response: 200,
        description: 'Statistiques globales',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'totalUsers', type: 'integer'),
                        new OA\Property(property: 'totalBookings', type: 'integer'),
                        new OA\Property(property: 'totalRevenue', type: 'number')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function stats(UserRepository $userRepository, BookingRepository $bookingRepository): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'totalUsers' => count($userRepository->findAll()),
                'totalBookings' => count($bookingRepository->findAll()),
                'message' => 'Statistiques à implémenter'
            ]
        ]);
    }
}
