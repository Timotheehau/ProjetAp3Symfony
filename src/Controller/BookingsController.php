<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\SessionHistory;
use App\Repository\AvailabilityRepository;
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
        $profileId = $request->query->get('profileId');
        $status = $request->query->get('status');
        $isCoachQuery = $request->query->has('isCoach');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');

        // LOGIQUE DE FILTRAGE PRIORITAIRE
        if ($profileId) {
            // Cas du planning : on veut voir les bookings d'un coach spécifique
            // On ne filtre PAS par l'utilisateur connecté ici pour que n'importe qui
            // puisse voir si le créneau est pris.
            $queryBuilder->andWhere('b.profile = :profileId')
                ->setParameter('profileId', $profileId);
        } elseif ($isCoachQuery) {
            // Cas du dashboard coach : "Je veux voir MES séances à moi"
            $queryBuilder->andWhere('b.profile = :myProfile')
                ->setParameter('myProfile', $user->getProfile());
        } else {
            // Cas du dashboard élève : "Je veux voir MES réservations"
            $queryBuilder->andWhere('b.client = :me')
                ->setParameter('me', $user);
        }

        // Filtre de statut (optionnel)
        if ($status) {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        $queryBuilder->orderBy('b.startTime', 'DESC');
        $bookings = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($booking) {
            // On récupère l'historique s'il existe
            $history = $booking->getSessionHistory();

            return [
                'id' => $booking->getId(),
                'startTime' => $booking->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $booking->getEndTime()->format('Y-m-d H:i:s'),
                'status' => $booking->getStatus(),
                'hasReview' => $booking->getReview() !== null,
                'totalPrice' => $booking->getTotalPrice(),
                'notes' => $booking->getNotes(),

                // AJOUT DE L'OBJET SESSION HISTORY
                'sessionHistory' => $history ? [
                    'id' => $history->getId(),
                    'professionalFeedback' => $history->getProfessionalFeedback(),
                    'clientFeedback' => $history->getClientFeedback(),
                ] : null,

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
                    'user' => [
                        'firstName' => $booking->getProfile()->getUser()->getFirstName(),
                        'lastName' => $booking->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ]
        ]);
    }
/*
    #[Route('/notifications/count', name: 'notifications_count', methods: ['GET'])]
    public function getNotificationCount(BookingRepository $bookingRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['total' => 0]);

        if ($user->getUserType() === 'professional') {
            // COMPTEUR COACH : On compte UNIQUEMENT les demandes non vues
            $total = $bookingRepo->count([
                'profile' => $user->getProfile(),
                'status' => 'pending',
                'isStatusSeenByClient' => false
            ]);
        } else {
            // COMPTEUR CLIENT
            $newConfirmations = $bookingRepo->count([
                'client' => $user,
                'status' => 'confirmed',
                'isStatusSeenByClient' => false
            ]);

            $newFeedbacks = (int)$em->getRepository(\App\Entity\SessionHistory::class)->createQueryBuilder('sh')
                ->select('count(sh.id)')
                ->where('sh.client = :user')
                ->andWhere('sh.isReadByClient = false')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $total = $newConfirmations + $newFeedbacks;
        }

        return $this->json(['total' => $total]);
    }
    #[Route('/notifications/delete-bulk', name: 'notifications_delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $id = (int)$item['id'];
            $type = $item['type'];

            // On regroupe tous les types qui concernent la table 'Booking'
            // 'alert' (annulation), 'request' (nouvelle demande), 'confirmation' (match accepté)
            if (in_array($type, ['confirmation', 'request', 'alert'])) {
                $booking = $em->getRepository(\App\Entity\Booking::class)->find($id);
                if ($booking) {
                    // On marque comme "vu" pour qu'il disparaisse du filtre PHP au prochain refresh
                    $booking->setIsStatusSeenByClient(true);
                }
            }
            // Cas particulier des bilans
            elseif ($type === 'feedback') {
                $history = $em->getRepository(\App\Entity\SessionHistory::class)->find($id);
                if ($history) {
                    $history->setIsReadByClient(true);
                }
            }
        }

        $em->flush(); // On valide les changements en une seule fois
        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-as-seen', name: 'bookings_mark_seen', methods: ['POST'])]
    public function markAllAsSeen(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        // 1. Marquer les statuts de bookings comme vus
        $em->createQueryBuilder()
            ->update(\App\Entity\Booking::class, 'b')
            ->set('b.isStatusSeenByClient', 'true')
            ->where('b.client = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->execute();

        // 2. Marquer les feedbacks comme vus
        $em->createQueryBuilder()
            ->update(\App\Entity\SessionHistory::class, 'sh')
            ->set('sh.isReadByClient', 'true')
            ->where('sh.client = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return $this->json(['success' => true]);
    }
    #[Route('/mark-one-seen', name: 'bookings_mark_one_seen', methods: ['POST'])]
    public function markOneSeen(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $type = $data['type'] ?? '';

        // Utilisation des noms de classe complets pour éviter les erreurs d'import
        if (in_array($type, ['confirmation', 'request', 'alert'])) {
            $repo = $em->getRepository(\App\Entity\Booking::class);
            $item = $repo->find($id);

            if ($item) {
                $item->setIsStatusSeenByClient(true);
                // On force Doctrine à voir que l'objet a changé
                $em->persist($item);
            }
        } elseif ($type === 'feedback') {
            $repo = $em->getRepository(\App\Entity\SessionHistory::class);
            $item = $repo->find($id);

            if ($item) {
                $item->setIsReadByClient(true);
                $em->persist($item);
            }
        }

        // Le flush DOIT être ici pour valider la transaction SQL
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/notifications/list', name: 'notifications_list', methods: ['GET'])]
    public function getNotificationList(BookingRepository $bookingRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $notifications = [];
        $limitDate = new \DateTimeImmutable('-7 days');

        if ($user->getUserType() === 'professional') {
            $profile = $user->getProfile();

            $bookings = $bookingRepo->createQueryBuilder('b')
                ->where('b.profile = :profile')
                ->andWhere('b.status IN (:statuses)')
                ->andWhere('b.isStatusSeenByClient = false') // <--- FILTRE AJOUTÉ
                ->andWhere('b.createdAt >= :limit OR b.updatedAt >= :limit')
                ->setParameter('profile', $profile)
                ->setParameter('statuses', ['pending', 'cancelled'])
                ->setParameter('limit', $limitDate)
                ->getQuery()->getResult();

            foreach ($bookings as $b) {
                $notifications[] = [
                    'type' => $b->getStatus() === 'pending' ? 'request' : 'alert',
                    'id' => $b->getId(),
                    'message' => $b->getStatus() === 'pending'
                        ? "Nouvelle demande : {$b->getClient()->getFirstName()}..."
                        : "Alerte : {$b->getClient()->getFirstName()} a annulé...",
                    'date' => $b->getUpdatedAt() ? $b->getUpdatedAt()->format('Y-m-d H:i:s') : $b->getCreatedAt()->format('Y-m-d H:i:s'),
                    'isRead' => false // On sait que c'est false grâce au filtre au-dessus
                ];
            }
        } else {
            // --- PARTIE CLIENT ---
            $bookings = $bookingRepo->createQueryBuilder('b')
                ->where('b.client = :user')
                ->andWhere('b.status IN (:statuses)')
                ->andWhere('b.isStatusSeenByClient = false') // <--- FILTRE AJOUTÉ
                ->setParameter('user', $user)
                ->setParameter('statuses', ['confirmed', 'cancelled'])
                ->orderBy('b.updatedAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            foreach ($bookings as $b) {
                $notifications[] = [
                    'type' => $b->getStatus() === 'confirmed' ? 'confirmation' : 'alert',
                    'id' => $b->getId(),
                    'message' => $b->getStatus() === 'confirmed'
                        ? "Match confirmé avec {$b->getProfile()->getUser()->getFirstName()} le {$b->getStartTime()->format('d/m')}."
                        : "Match annulé : la séance du {$b->getStartTime()->format('d/m')} n'aura pas lieu.",
                    'date' => $b->getUpdatedAt() ? $b->getUpdatedAt()->format('Y-m-d H:i:s') : $b->getStartTime()->format('Y-m-d H:i:s'),
                    'isRead' => false
                ];
            }

            // Bilans (Feedbacks)
            $feedbacks = $em->getRepository(\App\Entity\SessionHistory::class)->findBy(
                ['client' => $user, 'isReadByClient' => false], // <--- FILTRE AJOUTÉ ICI AUSSI
                ['id' => 'DESC'],
                10
            );

            foreach ($feedbacks as $sh) {
                $notifications[] = [
                    'type' => 'feedback',
                    'id' => $sh->getId(),
                    'message' => "Nouveau bilan disponible pour votre séance du {$sh->getSessionDate()->format('d/m')}.",
                    'date' => $sh->getSessionDate()->format('Y-m-d H:i:s'),
                    'isRead' => false
                ];
            }
        }

        usort($notifications, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $this->json($notifications);
    }
*/
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
        AvailabilityRepository $availabilityRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        // 1. Récupération du créneau source
        $availability = $availabilityRepo->find($data['availabilityId'] ?? null);
        if (!$availability) {
            return $this->json(['success' => false, 'message' => 'Créneau introuvable'], 404);
        }

        // 2. Définition des objets DateTime (Correction de l'erreur)
        try {
            $startTime = new \DateTime($data['startTime']);
            $endTime = new \DateTime($data['endTime']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Format de date invalide'], 400);
        }

        // 3. Vérification de conflit sur la tranche entière
        $conflicts = $em->getRepository(Booking::class)->createQueryBuilder('b')
            ->where('b.profile = :profile')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('(b.startTime < :endTime AND b.endTime > :startTime)')
            ->setParameter('profile', $availability->getProfile())
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getResult();

        if (!empty($conflicts)) {
            return $this->json(['success' => false, 'message' => 'Ce créneau est déjà réservé'], 409);
        }

        // 4. Création du Booking
        $booking = new Booking();
        $booking->setClient($user);
        $booking->setProfile($availability->getProfile());
        $booking->setStartTime($startTime);
        $booking->setEndTime($endTime);
        $booking->setStatus('pending');
        $booking->setTotalPrice($data['totalPrice'] ?? 0);
        $booking->setCreatedAt(new \DateTimeImmutable());
        $booking->setNotes($data['notes'] ?? "");

        // 5. On bloque l'availability si elle n'est pas récurrente
        if (!$availability->isRecurring()) {
            $availability->setIsAvailable(false);
        }

        $em->persist($booking);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Demande envoyée !']);
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
    ): JsonResponse
    {
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

        $booking->setIsStatusSeenByClient(false);

        if ($newStatus === 'cancelled') {
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancellationReason($data['cancellationReason'] ?? 'Non spécifiée');

            // On identifie qui annule
            $user = $this->getUser();
            if ($user === $booking->getClient()) {
                $booking->setCancelledBy('client');
            } elseif ($user->getProfile() === $booking->getProfile()) {
                $booking->setCancelledBy('professional');
            }
        }

        if ($newStatus === 'completed') {
            $now = new \DateTimeImmutable();

            if ($booking->getStartTime() > $now) {
                return $this->json([
                    'success' => false,
                    'message' => "Vous ne pouvez pas terminer une séance qui n'a pas encore eu lieu."
                ], Response::HTTP_BAD_REQUEST);
            }
            $booking->setCompletedAt($now);

            // --- AUTOMATISATION SESSION HISTORY ---
            // 1. On vérifie si un historique n'existe pas déjà pour ce booking (évite les doublons)
            if (!$booking->getSessionHistory()) {
                $history = new SessionHistory();
                $history->setBooking($booking);
                $history->setProfile($booking->getProfile());
                $history->setClient($booking->getClient());
                $history->setSessionDate($booking->getStartTime());

                // 2. Calcul de la durée en minutes
                if ($booking->getStartTime() && $booking->getEndTime()) {
                    $durationInSeconds = $booking->getEndTime()->getTimestamp() - $booking->getStartTime()->getTimestamp();
                    $durationInMinutes = floor($durationInSeconds / 60);
                    $history->setDuration((int)$durationInMinutes);
                } else {
                    $history->setDuration(60); // Valeur par défaut si dates absentes
                }

                $history->setCreatedAt(new \DateTimeImmutable());

                // 3. On persiste le nouvel historique
                $em->persist($history);
            }
        }

        $em->flush(); // Le flush enregistre à la fois le Booking et le SessionHistory

        return $this->json([
            'success' => true,
            'message' => 'Statut mis à jour et historique créé'
        ]);
    }
}
