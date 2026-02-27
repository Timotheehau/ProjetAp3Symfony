<?php

namespace App\Controller;

use App\Repository\SessionHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
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
                'booking' => [
                    'id' => $session->getBooking()->getId(),
                    'status' => $session->getBooking()->getStatus(),
                ],
            ];
        }, $sessions);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }
    #[Route('/{id}/feedback', name: 'sessions_feedback', methods: ['POST'])]
    public function addFeedback(
        int $id,
        Request $request,
        SessionHistoryRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $repo->find($id);
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!$session) return $this->json(['message' => 'Session introuvable'], 404);

        // Si c'est le coach qui écrit
        if ($user->getProfile() === $session->getProfile()) {
            $session->setProfessionalFeedback($data['feedback']);
        }
        // Si c'est l'élève
        elseif ($user === $session->getClient()) {
            $session->setClientFeedback($data['feedback']);
        }

        $em->flush();
        return $this->json(['success' => true]);
    }
    #[Route('/{id}', name: 'sessions_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        SessionHistoryRepository $repository,
        EntityManagerInterface $em
    ): JsonResponse {
        $session = $repository->find($id);
        if (!$session) {
            return $this->json(['success' => false, 'message' => 'Session introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $user = $this->getUser(); // On récupère l'utilisateur connecté

        // Sécurité & Attribution du feedback
        // 1. Si c'est le pro (via son Profile)
        if ($user->getProfile() && $user->getProfile()->getId() === $session->getProfile()->getId()) {
            if (isset($data['professionalFeedback'])) {
                $session->setProfessionalFeedback($data['professionalFeedback']);
            }
        }
        // 2. Si c'est le client
        elseif ($user->getId() === $session->getClient()->getId()) {
            if (isset($data['clientFeedback'])) {
                $session->setClientFeedback($data['clientFeedback']);
            }
        } else {
            return $this->json(['success' => false, 'message' => 'Accès non autorisé'], 403);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Feedback enregistré avec succès'
        ]);
    }
}
