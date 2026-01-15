<?php

namespace App\Controller;

use App\Repository\VenueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/venues')]
#[OA\Tag(name: 'Venues')]
class VenuesController extends AbstractController
{
    #[Route('', name: 'venues_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/venues',
        summary: 'Liste tous les lieux (accès public)',
        tags: ['Venues']
    )]
    #[OA\Parameter(
        name: 'city',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Filtrer par ville'
    )]
    #[OA\Parameter(
        name: 'sportId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par sport'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des lieux',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'type', type: 'string'),
                            new OA\Property(property: 'address', type: 'string'),
                            new OA\Property(property: 'city', type: 'string'),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'isActive', type: 'boolean')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, VenueRepository $venueRepository): JsonResponse
    {
        $city = $request->query->get('city');
        $sportId = $request->query->get('sportId');

        $queryBuilder = $venueRepository->createQueryBuilder('v');

        if ($city) {
            $queryBuilder
                ->andWhere('v.city = :city')
                ->setParameter('city', $city);
        }

        if ($sportId) {
            $queryBuilder
                ->andWhere('v.sport = :sportId')
                ->setParameter('sportId', $sportId);
        }

        $venues = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($venue) {
            return [
                'id' => $venue->getId(),
                'name' => $venue->getName(),
                'type' => $venue->getType(),
                'address' => $venue->getAddress(),
                'city' => $venue->getCity(),
                'postalCode' => $venue->getPostalCode(),
                'latitude' => $venue->getLatitude(),
                'longitude' => $venue->getLongitude(),
                'capacity' => $venue->getCapacity(),
                'facilities' => $venue->getFacilities(),
                'contactEmail' => $venue->getContactEmail(),
                'contactPhone' => $venue->getContactPhone(),
                'website' => $venue->getWebsite(),
                'isActive' => $venue->isActive(),
            ];
        }, $venues);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'venues_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/venues/{id}',
        summary: 'Affiche un lieu spécifique',
        tags: ['Venues']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 200, description: 'Détails du lieu')]
    #[OA\Response(response: 404, description: 'Lieu non trouvé')]
    public function show(int $id, VenueRepository $venueRepository): JsonResponse
    {
        $venue = $venueRepository->find($id);
        
        if (!$venue) {
            return $this->json([
                'success' => false,
                'message' => 'Lieu non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $venue->getId(),
                'name' => $venue->getName(),
                'type' => $venue->getType(),
                'address' => $venue->getAddress(),
                'city' => $venue->getCity(),
                'postalCode' => $venue->getPostalCode(),
                'latitude' => $venue->getLatitude(),
                'longitude' => $venue->getLongitude(),
                'capacity' => $venue->getCapacity(),
                'facilities' => $venue->getFacilities(),
                'contactEmail' => $venue->getContactEmail(),
                'contactPhone' => $venue->getContactPhone(),
                'website' => $venue->getWebsite(),
                'isActive' => $venue->isActive(),
            ]
        ]);
    }
}
