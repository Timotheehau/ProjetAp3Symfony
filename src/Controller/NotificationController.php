<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\SessionHistory;
use App\Repository\BookingRepository;
use App\Repository\UserRepository; // Ajout de l'import
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('/list', name: 'notifications_list', methods: ['GET'])]
    public function list(BookingRepository $bookingRepo, UserRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json([], 401);

        $notifications = [];
        $limitDate = new \DateTimeImmutable('-7 days');

        // --- 1. LOGIQUE ADMIN : Coachs en attente de vérification ---
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $pendingCoaches = $userRepo->createQueryBuilder('u')
                ->join('u.profile', 'p')
                ->where('u.userType = :type')
                ->andWhere('p.isVerified = :verified')
                ->setParameter('type', 'professional')
                ->setParameter('verified', false)
                ->getQuery()->getResult();

            foreach ($pendingCoaches as $coach) {
                $notifications[] = [
                    'type' => 'request',
                    'id' => $coach->getId(),
                    'message' => "Vérification requise : {$coach->getFirstName()} {$coach->getLastName()} attend sa validation.",
                    'date' => $coach->getCreatedAt()->format('Y-m-d H:i:s'),
                    'isRead' => false,
                    'category' => 'admin_verify' // Tag pour aider le front à rediriger
                ];
            }
        }

        // --- 2. LOGIQUE COACH (Professional) ---
        if ($user->getUserType() === 'professional') {
            $profile = $user->getProfile();
            $bookings = $bookingRepo->createQueryBuilder('b')
                ->where('b.profile = :profile')
                ->andWhere('b.status IN (:statuses)')
                ->andWhere('b.isStatusSeenByClient = false')
                ->andWhere('b.createdAt >= :limit OR b.updatedAt >= :limit')
                ->setParameter('profile', $profile)
                ->setParameter('statuses', ['pending', 'cancelled'])
                ->setParameter('limit', $limitDate)
                ->getQuery()->getResult();

            foreach ($bookings as $b) {
                if ($b->getStatus() === 'cancelled' && $b->getCancelledBy() === 'professional') {
                    continue;
                }
                $message = $b->getStatus() === 'pending'
                    ? "Nouvelle demande : {$b->getClient()->getFirstName()} pour le {$b->getStartTime()->format('d/m')}"
                    : "Alerte : {$b->getClient()->getFirstName()} a annulé" . ($b->getCancellationReason() ? " ({$b->getCancellationReason()})" : "");

                $notifications[] = $this->formatNotification($b, $message, $b->getStatus() === 'pending' ? 'request' : 'alert');
            }
        }

        // --- 3. LOGIQUE ÉLÈVE (Particular) ---
        if ($user->getUserType() === 'particular') {
            $bookings = $bookingRepo->createQueryBuilder('b')
                ->where('b.client = :user')
                ->andWhere('b.status IN (:statuses)')
                ->andWhere('b.isStatusSeenByClient = false')
                ->setParameter('user', $user)
                ->setParameter('statuses', ['confirmed', 'cancelled'])
                ->getQuery()->getResult();

            foreach ($bookings as $b) {
                if ($b->getStatus() === 'cancelled' && $b->getCancelledBy() === 'client') {
                    continue;
                }
                $message = $b->getStatus() === 'confirmed'
                    ? "Match confirmé avec {$b->getProfile()->getUser()->getFirstName()} le {$b->getStartTime()->format('d/m')}."
                    : "Match annulé : {$b->getCancellationReason()}";

                $notifications[] = $this->formatNotification($b, $message, $b->getStatus() === 'confirmed' ? 'confirmation' : 'alert');
            }

            // Feedbacks
            $feedbacks = $em->getRepository(SessionHistory::class)->findBy(['client' => $user, 'isReadByClient' => false]);
            foreach ($feedbacks as $sh) {
                $notifications[] = [
                    'type' => 'feedback',
                    'id' => $sh->getId(),
                    'message' => "Nouveau bilan pour votre séance du {$sh->getSessionDate()->format('d/m')}.",
                    'date' => $sh->getSessionDate()->format('Y-m-d H:i:s'),
                    'isRead' => false
                ];
            }
        }

        // --- 4. RAPPELS J-2 : réservations confirmées qui démarrent dans moins de 2 jours ---
        foreach ($this->findUpcomingReminderBookings($bookingRepo, $user) as $b) {
            $isProfessional = $user->getUserType() === 'professional';
            $otherPartyName = $isProfessional
                ? $b->getClient()->getFirstName()
                : $b->getProfile()->getUser()->getFirstName();

            $notifications[] = [
                'type' => 'reminder',
                'id' => $b->getId(),
                'message' => "Rappel : séance avec {$otherPartyName} le {$b->getStartTime()->format('d/m à H:i')}.",
                'date' => $b->getStartTime()->format('Y-m-d H:i:s'),
                'isRead' => false
            ];
        }

        // Tri par date décroissante (plus récent en haut)
        usort($notifications, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $this->json($notifications);
    }

    /**
     * @return Booking[] Réservations confirmées de l'utilisateur démarrant dans les 2 prochains jours
     */
    private function findUpcomingReminderBookings(BookingRepository $bookingRepo, $user): array
    {
        if (!in_array($user->getUserType(), ['professional', 'particular'])) {
            return [];
        }

        if ($user->getUserType() === 'professional' && !$user->getProfile()) {
            return [];
        }

        $qb = $bookingRepo->createQueryBuilder('b')
            ->where('b.status = :confirmed')
            ->andWhere('b.startTime > :now')
            ->andWhere('b.startTime <= :limit')
            ->setParameter('confirmed', 'confirmed')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('limit', new \DateTimeImmutable('+2 days'));

        if ($user->getUserType() === 'professional') {
            $qb->andWhere('b.profile = :profile')->setParameter('profile', $user->getProfile());
        } else {
            $qb->andWhere('b.client = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    #[Route('/count', name: 'notifications_count', methods: ['GET'])]
    public function count(BookingRepository $bookingRepo, UserRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['total' => 0]);

        $total = 0;

        // Compteur Admin
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $total += $userRepo->createQueryBuilder('u')
                ->select('count(u.id)')
                ->join('u.profile', 'p')
                ->where('u.userType = :type AND p.isVerified = false')
                ->setParameter('type', 'professional')
                ->getQuery()->getSingleScalarResult();
        }

        // Compteur Coach
        if ($user->getUserType() === 'professional') {
            $total += $bookingRepo->count(['profile' => $user->getProfile(), 'status' => 'pending', 'isStatusSeenByClient' => false]);
        }

        // Compteur Élève
        if ($user->getUserType() === 'particular') {
            $newConfirmations = $bookingRepo->count(['client' => $user, 'status' => 'confirmed', 'isStatusSeenByClient' => false]);
            $newFeedbacks = (int)$em->getRepository(SessionHistory::class)->createQueryBuilder('sh')
                ->select('count(sh.id)')->where('sh.client = :user AND sh.isReadByClient = false')
                ->setParameter('user', $user)->getQuery()->getSingleScalarResult();
            $total += ($newConfirmations + $newFeedbacks);
        }

        // Compteur rappels J-2 (coach et élève)
        $total += count($this->findUpcomingReminderBookings($bookingRepo, $user));

        return $this->json(['total' => $total]);
    }

    #[Route('/mark-seen', name: 'notifications_mark_seen', methods: ['POST'])]
    public function markSeen(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $data['id'] ?? null;
        $type = $data['type'] ?? '';

        if (in_array($type, ['confirmation', 'request', 'alert'])) {
            $item = $em->getRepository(Booking::class)->find($id);
            if ($item) $item->setIsStatusSeenByClient(true);
        } elseif ($type === 'feedback') {
            $item = $em->getRepository(SessionHistory::class)->find($id);
            if ($item) $item->setIsReadByClient(true);
        }

        $em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/mark-all-as-seen', name: 'notifications_mark_all_seen', methods: ['POST'])]
    public function markAllAsSeen(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['success' => false], 401);

        $qbBooking = $em->createQueryBuilder();
        $queryBooking = $qbBooking->update(Booking::class, 'b')
            ->set('b.isStatusSeenByClient', 'true')
            ->where($user->getUserType() === 'professional' ? 'b.profile = :profile' : 'b.client = :user')
            ->andWhere('b.isStatusSeenByClient = false');

        if ($user->getUserType() === 'professional') {
            $queryBooking->setParameter('profile', $user->getProfile());
        } else {
            $queryBooking->setParameter('user', $user);
        }
        $queryBooking->getQuery()->execute();

        if ($user->getUserType() === 'particular') {
            $em->createQueryBuilder()
                ->update(SessionHistory::class, 'sh')
                ->set('sh.isReadByClient', 'true')
                ->where('sh.client = :user')
                ->andWhere('sh.isReadByClient = false')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();
        }

        return $this->json(['success' => true, 'message' => 'Toutes les notifications sont marquées comme lues']);
    }

    #[Route('/delete-bulk', name: 'notifications_delete_bulk', methods: ['POST'])]
    public function deleteBulk(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $type = $item['type'] ?? '';

            if (in_array($type, ['confirmation', 'request', 'alert'])) {
                $booking = $em->getRepository(Booking::class)->find($id);
                if ($booking) $booking->setIsStatusSeenByClient(true);
            } elseif ($type === 'feedback') {
                $history = $em->getRepository(SessionHistory::class)->find($id);
                if ($history) $history->setIsReadByClient(true);
            }
        }

        $em->flush();
        return $this->json(['success' => true]);
    }

    private function formatNotification($booking, $message, $type): array {
        return [
            'type' => $type,
            'id' => $booking->getId(),
            'message' => $message,
            'date' => $booking->getUpdatedAt() ? $booking->getUpdatedAt()->format('Y-m-d H:i:s') : $booking->getCreatedAt()->format('Y-m-d H:i:s'),
            'isRead' => false
        ];
    }
}
