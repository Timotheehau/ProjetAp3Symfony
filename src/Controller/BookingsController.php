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
use OpenApi\Attributes as OA;

#[Route('/api/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingsController extends AbstractController
{
    #[Route('', name: 'bookings_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings',
        summary: 'Liste toutes les réservations avec filtres optionnels',
        security: [['Bearer' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'userId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID client'
    )]
    #[OA\Parameter(
        name: 'profileId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par ID professionnel'
    )]
    #[OA\Parameter(
        name: 'status',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed']),
        description: 'Filtrer par statut'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des réservations',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'startTime', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'endTime', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'totalPrice', type: 'string'),
                            new OA\Property(property: 'notes', type: 'string')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, BookingRepository $bookingRepository): JsonResponse
    {
        $user = $this->getUser();
        $userId = $request->query->get('userId');
        $profileId = $request->query->get('profileId');
        $status = $request->query->get('status');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');

        // Sécurité : On filtre TOUJOURS par l'utilisateur connecté
        // Sauf si c'est un coach qui veut voir ses demandes (profileId)
        if ($request->query->has('isCoach')) {
            $queryBuilder->andWhere('b.profile = :profile')
                ->setParameter('profile', $user->getProfile());
        } else {
            $queryBuilder->andWhere('b.client = :user')
                ->setParameter('user', $user);
        }

        if ($status) {
            $queryBuilder->andWhere('b.status = :status')->setParameter('status', $status);
        }

        $queryBuilder->orderBy('b.startTime', 'DESC');

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
    #[OA\Get(
        path: '/api/bookings/{id}',
        summary: 'Affiche une réservation spécifique',
        security: [['Bearer' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 200, description: 'Détails de la réservation')]
    #[OA\Response(response: 404, description: 'Réservation non trouvée')]
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
    #[OA\Post(
        path: '/api/bookings',
        summary: 'Créer une nouvelle réservation',
        security: [['Bearer' => []]],
        tags: ['Bookings']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['clientId', 'profileId', 'startTime', 'endTime'],
            properties: [
                new OA\Property(property: 'clientId', type: 'integer', example: 1),
                new OA\Property(property: 'profileId', type: 'integer', example: 1),
                new OA\Property(property: 'startTime', type: 'string', format: 'date-time', example: '2025-01-30 10:00:00'),
                new OA\Property(property: 'endTime', type: 'string', format: 'date-time', example: '2025-01-30 12:00:00'),
                new OA\Property(property: 'notes', type: 'string', example: 'Cours de tennis débutant'),
                new OA\Property(property: 'venueId', type: 'integer', example: 1, nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Réservation créée avec succès')]
    #[OA\Response(response: 400, description: 'Données invalides')]
    #[OA\Response(response: 409, description: 'Conflit de disponibilité')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository,
        VenueRepository $venueRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser(); // On récupère l'utilisateur connecté

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $profile = $profileRepository->find($data['profileId'] ?? null);
        if (!$profile) {
            return $this->json(['success' => false, 'message' => 'Professionnel non trouvé'], 400);
        }

        // Conversion des dates envoyées par SportDetail.jsx
        try {
            $startTime = new \DateTime($data['date'] . ' ' . $data['startTime']);
            // On calcule le endTime (par exemple +1h par défaut ou basé sur le slot)
            $endTime = new \DateTime($data['date'] . ' ' . $data['endTime']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Format de date invalide'], 400);
        }

        // --- Vérifier disponibilité (Ton code de conflit est très bien, on le garde) ---
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
            return $this->json(['success' => false, 'message' => 'Ce créneau vient d\'être réservé !'], 409);
        }

        $booking = new Booking();
        $booking->setClient($user); // Sécurité : c'est l'user connecté
        $booking->setProfile($profile);
        $booking->setStartTime($startTime);
        $booking->setEndTime($endTime);
        $booking->setStatus('pending');
        $booking->setNotes($data['notes'] ?? "Match réservé via .MATCH");

        // MODIFICATION : On met le prix à "0" ou "Donation"
        $booking->setTotalPrice("0");
        $booking->setCreatedAt(new \DateTimeImmutable());

        $em->persist($booking);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Demande de match envoyée !',
            'data' => ['id' => $booking->getId(), 'status' => $booking->getStatus()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', name: 'bookings_update_status', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/bookings/{id}/status',
        summary: 'Mettre à jour le statut d\'une réservation',
        security: [['Bearer' => []]],
        tags: ['Bookings']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled', 'completed']),
                new OA\Property(property: 'cancellationReason', type: 'string', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Statut mis à jour avec succès')]
    #[OA\Response(response: 404, description: 'Réservation non trouvée')]
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
