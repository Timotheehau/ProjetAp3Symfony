<?php

namespace App\Controller;

use App\Repository\SessionHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/sessions')]
#[OA\Tag(name: 'Sessions')]
class SessionsHistoryController extends AbstractController
{
    #[Route('', name: 'sessions_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/sessions',
        summary: 'Liste l\'historique des sessions',
        security: [['Bearer' => []]],
        tags: ['Sessions']
    )]
    #[OA\Parameter(
        name: 'profileId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par profil professionnel'
    )]
    #[OA\Parameter(
        name: 'clientId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par client'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des sessions',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'sessionDate', type: 'string'),
                            new OA\Property(property: 'duration', type: 'integer'),
                            new OA\Property(property: 'notes', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, SessionHistoryRepository $sessionHistoryRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        $clientId = $request->query->get('clientId');

        $queryBuilder = $sessionHistoryRepository->createQueryBuilder('s');

        if ($profileId) {
            $queryBuilder
                ->andWhere('s.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }

        if ($clientId) {
            $queryBuilder
                ->andWhere('s.client = :clientId')
                ->setParameter('clientId', $clientId);
        }

        $queryBuilder->orderBy('s.sessionDate', 'DESC');
        $sessions = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($session) {
            return [
                'id' => $session->getId(),
                'sessionDate' => $session->getSessionDate()->format('Y-m-d H:i:s'),
                'duration' => $session->getDuration(),
                'notes' => $session->getNotes(),
                'clientFeedback' => $session->getClientFeedback(),
                'professionalFeedback' => $session->getProfessionalFeedback(),
                'createdAt' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $sessions);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
