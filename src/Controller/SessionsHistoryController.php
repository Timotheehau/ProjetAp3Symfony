<?php

namespace App\Controller;

use App\Entity\SessionHistory;
use App\Repository\SessionHistoryRepository;
use App\Repository\BookingRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sessions-history')]
class SessionsHistoryController extends AbstractController
{
    #[Route('', name: 'sessions_history_list', methods: ['GET'])]
    public function list(Request $request, SessionHistoryRepository $sessionHistoryRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        $clientId = $request->query->get('clientId');
        
        $queryBuilder = $sessionHistoryRepository->createQueryBuilder('sh');
        
        if ($profileId) {
            $queryBuilder->andWhere('sh.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }
        
        if ($clientId) {
            $queryBuilder->andWhere('sh.client = :clientId')
                ->setParameter('clientId', $clientId);
        }
        
        $queryBuilder->orderBy('sh.sessionDate', 'DESC');
        $sessions = $queryBuilder->getQuery()->getResult();
        
        $data = array_map(function($session) {
            return [
                'id' => $session->getId(),
                'sessionDate' => $session->getSessionDate()->format('Y-m-d H:i:s'),
                'duration' => $session->getDuration(),
                'notes' => $session->getNotes(),
                'clientFeedback' => $session->getClientFeedback(),
                'professionalFeedback' => $session->getProfessionalFeedback(),
                'client' => [
                    'id' => $session->getClient()->getId(),
                    'firstName' => $session->getClient()->getFirstName(),
                    'lastName' => $session->getClient()->getLastName(),
                ],
                'profile' => [
                    'id' => $session->getProfile()->getId(),
                    'specialty' => $session->getProfile()->getSpecialty(),
                ],
            ];
        }, $sessions);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'sessions_history_show', methods: ['GET'])]
    public function show(int $id, SessionHistoryRepository $sessionHistoryRepository): JsonResponse
    {
        $session = $sessionHistoryRepository->find($id);
        
        if (!$session) {
            return $this->json([
                'success' => false,
                'message' => 'Session non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $session->getId(),
                'sessionDate' => $session->getSessionDate()->format('Y-m-d H:i:s'),
                'duration' => $session->getDuration(),
                'notes' => $session->getNotes(),
                'clientFeedback' => $session->getClientFeedback(),
                'professionalFeedback' => $session->getProfessionalFeedback(),
                'booking' => [
                    'id' => $session->getBooking()->getId(),
                    'totalPrice' => $session->getBooking()->getTotalPrice(),
                ],
                'client' => [
                    'firstName' => $session->getClient()->getFirstName(),
                    'lastName' => $session->getClient()->getLastName(),
                ],
                'profile' => [
                    'specialty' => $session->getProfile()->getSpecialty(),
                    'user' => [
                        'firstName' => $session->getProfile()->getUser()->getFirstName(),
                        'lastName' => $session->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ]
        ]);
    }

    #[Route('', name: 'sessions_history_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        BookingRepository $bookingRepository,
        ProfileRepository $profileRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $booking = $bookingRepository->find($data['bookingId'] ?? null);
        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($booking->getStatus() !== 'completed') {
            return $this->json([
                'success' => false,
                'message' => 'La réservation doit être complétée'
            ], Response::HTTP_BAD_REQUEST);
        }

        $session = new SessionHistory();
        $session->setBooking($booking);
        $session->setProfile($booking->getProfile());
        $session->setClient($booking->getClient());
        $session->setSessionDate($booking->getStartTime());
        
        $interval = $booking->getStartTime()->diff($booking->getEndTime());
        $duration = ($interval->h * 60) + $interval->i;
        $session->setDuration($duration);
        
        $session->setNotes($data['notes'] ?? null);

        $em->persist($session);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Historique de session créé',
            'data' => ['id' => $session->getId()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/feedback', name: 'sessions_history_feedback', methods: ['PATCH'])]
    public function addFeedback(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        SessionHistoryRepository $sessionHistoryRepository
    ): JsonResponse {
        $session = $sessionHistoryRepository->find($id);
        
        if (!$session) {
            return $this->json([
                'success' => false,
                'message' => 'Session non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['clientFeedback'])) {
            $session->setClientFeedback($data['clientFeedback']);
        }

        if (isset($data['professionalFeedback'])) {
            $session->setProfessionalFeedback($data['professionalFeedback']);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Feedback ajouté avec succès'
        ]);
    }
}
