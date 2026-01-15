<?php

namespace App\Controller;

use App\Repository\AvailabilityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/availabilities')]
#[OA\Tag(name: 'Availabilities')]
class AvailabilitiesController extends AbstractController
{
    #[Route('', name: 'availabilities_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/availabilities',
        summary: 'Liste toutes les disponibilités',
        security: [['Bearer' => []]],
        tags: ['Availabilities']
    )]
    #[OA\Parameter(
        name: 'profileId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par profil professionnel'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des disponibilités',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'dayOfWeek', type: 'integer'),
                            new OA\Property(property: 'startTime', type: 'string'),
                            new OA\Property(property: 'endTime', type: 'string'),
                            new OA\Property(property: 'isRecurring', type: 'boolean'),
                            new OA\Property(property: 'isAvailable', type: 'boolean')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, AvailabilityRepository $availabilityRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');

        $queryBuilder = $availabilityRepository->createQueryBuilder('a');

        if ($profileId) {
            $queryBuilder
                ->andWhere('a.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }

        $availabilities = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($availability) {
            return [
                'id' => $availability->getId(),
                'dayOfWeek' => $availability->getDayOfWeek(),
                'startTime' => $availability->getStartTime()->format('H:i:s'),
                'endTime' => $availability->getEndTime()->format('H:i:s'),
                'isRecurring' => $availability->isRecurring(),
                'specificDate' => $availability->getSpecificDate()?->format('Y-m-d'),
                'isAvailable' => $availability->isAvailable(),
            ];
        }, $availabilities);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('', name: 'availabilities_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/availabilities',
        summary: 'Créer une nouvelle disponibilité',
        security: [['Bearer' => []]],
        tags: ['Availabilities']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['profileId', 'startTime', 'endTime'],
            properties: [
                new OA\Property(property: 'profileId', type: 'integer'),
                new OA\Property(property: 'dayOfWeek', type: 'integer', minimum: 0, maximum: 6, nullable: true),
                new OA\Property(property: 'startTime', type: 'string', format: 'time', example: '09:00'),
                new OA\Property(property: 'endTime', type: 'string', format: 'time', example: '17:00'),
                new OA\Property(property: 'isRecurring', type: 'boolean', example: true),
                new OA\Property(property: 'specificDate', type: 'string', format: 'date', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Disponibilité créée avec succès')]
    public function create(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Méthode à implémenter'
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
