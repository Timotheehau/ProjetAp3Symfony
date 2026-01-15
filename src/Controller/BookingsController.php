<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Repository\UserRepository;
use App\Repository\ProfileRepository;
use App\Repository\VenueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/bookings')]
class BookingsController extends AbstractController
{
    #[Route('', name: 'bookings_list', methods: ['GET'])]
    public function list(Request $request, BookingRepository $bookingRepository): JsonResponse
    {
        $userId = $request->query->get('userId');
        $profileId = $request->query->get('profileId');
        $status = $request->query->get('status');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');

        if ($userId) {
            $queryBuilder->andWhere('b.client = :userId')
                ->setParameter('userId', $userId);
        }

        if ($profileId) {
            $queryBuilder->andWhere('b.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }

        if ($status) {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        $queryBuilder->orderBy('b.startTime', 'DESC');
        $bookings = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($booking) {
            return [
                'id' => $booking->getId(),
                'startTime' => $booking->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $booking->getEndTime()->format('Y-m-d H:i:s'),
                'status' => $booking->getStatus(),
                'totalPrice' => $booking->getTotalPrice(),
                'notes' => $booking->getNotes(),
                'client' => [
                    'id' => $booking->getClient()->getId(),
                    'firstName' => $booking->getClient()->getFirstName(),
                    'lastName' => $booking->getClient()->getLastName(),
                ],
                'profile' => [
                    'id' => $booking->getProfile()->getId(),
                    'specialty' => $booking->getProfile()->getSpecialty(),
                    'user' => [
                        'firstName' => $booking->getProfile()->getUser()->getFirstName(),
                        'lastName' => $booking->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ];
        }, $bookings);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'bookings_show', methods: ['GET'])]
    public function show(int $id, BookingRepository $bookingRepository): JsonResponse
    {
        $booking = $bookingRepository->find($id);
        
        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $booking->getId(),
                'startTime' => $booking->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $booking->getEndTime()->format('Y-m-d H:i:s'),
                'status' => $booking->getStatus(),
                'totalPrice' => $booking->getTotalPrice(),
                'notes' => $booking->getNotes(),
                'cancellationReason' => $booking->getCancellationReason(),
                'client' => [
                    'id' => $booking->getClient()->getId(),
                    'firstName' => $booking->getClient()->getFirstName(),
                    'lastName' => $booking->getClient()->getLastName(),
                    'email' => $booking->getClient()->getEmail(),
                ],
                'profile' => [
                    'id' => $booking->getProfile()->getId(),
                    'specialty' => $booking->getProfile()->getSpecialty(),
                    'hourlyRate' => $booking->getProfile()->getHourlyRate(),
                    'user' => [
                        'firstName' => $booking->getProfile()->getUser()->getFirstName(),
                        'lastName' => $booking->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ]
        ]);
    }

    #[Route('', name: 'bookings_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        VenueRepository $venueRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $client = $userRepository->find($data['clientId'] ?? null);
        if (!$client) {
            return $this->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $profile = $profileRepository->find($data['profileId'] ?? null);
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Professionnel non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $startTime = new \DateTime($data['startTime']);
        $endTime = new \DateTime($data['endTime']);

        // Vérifier disponibilité
        $conflicts = $em->getRepository(Booking::class)->createQueryBuilder('b')
            ->where('b.profile = :profile')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('(b.startTime < :endTime AND b.endTime > :startTime)')
            ->setParameter('profile', $profile)
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getResult();

        if (!empty($conflicts)) {
            return $this->json([
                'success' => false,
                'message' => 'Le professionnel n\'est pas disponible à cette période'
            ], Response::HTTP_CONFLICT);
        }

        $booking = new Booking();
        $booking->setClient($client);
        $booking->setProfile($profile);
        $booking->setStartTime($startTime);
        $booking->setEndTime($endTime);
        $booking->setStatus('pending');
        $booking->setNotes($data['notes'] ?? null);

        if (isset($data['venueId'])) {
            $venue = $venueRepository->find($data['venueId']);
            if ($venue) {
                $booking->setVenue($venue);
            }
        }

        // Calculer le prix
        $interval = $startTime->diff($endTime);
        $hours = $interval->h + ($interval->i / 60);
        $totalPrice = $hours * floatval($profile->getHourlyRate());
        $booking->setTotalPrice((string)$totalPrice);

        $booking->setCreatedAt(new \DateTimeImmutable());

        $em->persist($booking);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Réservation créée avec succès',
            'data' => [
                'id' => $booking->getId(),
                'totalPrice' => $booking->getTotalPrice(),
                'status' => $booking->getStatus(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', name: 'bookings_update_status', methods: ['PATCH'])]
    public function updateStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $booking = $bookingRepository->find($id);
        
        if (!$booking) {
            return $this->json([
                'success' => false,
                'message' => 'Réservation non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed'])) {
            return $this->json([
                'success' => false,
                'message' => 'Statut invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking->setStatus($newStatus);

        if ($newStatus === 'cancelled') {
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancellationReason($data['cancellationReason'] ?? null);
        }

        if ($newStatus === 'completed') {
            $booking->setCompletedAt(new \DateTimeImmutable());
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès'
        ]);
    }
}
