<?php

namespace App\Controller;

use App\Repository\DonationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/donations')]
#[OA\Tag(name: 'Donations')]
class DonationsController extends AbstractController
{
    #[Route('', name: 'donations_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/donations',
        summary: 'Liste tous les dons',
        security: [['Bearer' => []]],
        tags: ['Donations']
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par utilisateur'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des dons',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'amount', type: 'number'),
                            new OA\Property(property: 'currency', type: 'string'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'isAnonymous', type: 'boolean'),
                            new OA\Property(property: 'createdAt', type: 'string')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, DonationRepository $donationRepository): JsonResponse
    {
        $userId = $request->query->get('userId');

        $queryBuilder = $donationRepository->createQueryBuilder('d');

        if ($userId) {
            $queryBuilder
                ->andWhere('d.user = :userId')
                ->setParameter('userId', $userId);
        }

        $queryBuilder->orderBy('d.createdAt', 'DESC');
        $donations = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($donation) {
            return [
                'id' => $donation->getId(),
                'amount' => $donation->getAmount(),
                'currency' => $donation->getCurrency(),
                'message' => $donation->getMessage(),
                'isAnonymous' => $donation->isAnonymous(),
                'status' => $donation->getStatus(),
                'paymentMethod' => $donation->getPaymentMethod(),
                'createdAt' => $donation->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $donations);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('', name: 'donations_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/donations',
        summary: 'Créer un nouveau don',
        security: [['Bearer' => []]],
        tags: ['Donations']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['amount'],
            properties: [
                new OA\Property(property: 'amount', type: 'number', example: 10.00),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'message', type: 'string', nullable: true),
                new OA\Property(property: 'isAnonymous', type: 'boolean', example: false),
                new OA\Property(property: 'paymentMethod', type: 'string', example: 'card')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Don créé avec succès')]
    #[OA\Response(response: 400, description: 'Données invalides')]
    public function create(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Méthode à implémenter'
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
