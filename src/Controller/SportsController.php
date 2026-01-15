<?php

namespace App\Controller;

use App\Repository\SportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/sports')]
#[OA\Tag(name: 'Sports')]
class SportsController extends AbstractController
{
    #[Route('', name: 'sports_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/sports',
        summary: 'Liste tous les sports (accès public)',
        tags: ['Sports']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des sports',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'name', type: 'string', example: 'Tennis'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'icon', type: 'string'),
                            new OA\Property(property: 'isActive', type: 'boolean', example: true)
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(SportRepository $sportRepository): JsonResponse
    {
        $sports = $sportRepository->findAll();
        
        $data = array_map(function($sport) {
            return [
                'id' => $sport->getId(),
                'name' => $sport->getName(),
                'description' => $sport->getDescription(),
                'icon' => $sport->getIcon(),
                'isActive' => $sport->isActive(),
            ];
        }, $sports);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
