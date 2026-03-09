<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\SessionHistory;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    #[Route('/list', name: 'notifications_list', methods: ['GET'])]
    public function list(BookingRepository $bookingRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json([], 401);

        $notifications = [];
        $limitDate = new \DateTimeImmutable('-7 days');

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
        } else {
            // LOGIQUE CLIENT
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

        usort($notifications, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $this->json($notifications);
    }

    #[Route('/count', name: 'notifications_count', methods: ['GET'])]
    public function count(BookingRepository $bookingRepo, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json(['total' => 0]);

        if ($user->getUserType() === 'professional') {
            $total = $bookingRepo->count(['profile' => $user->getProfile(), 'status' => 'pending', 'isStatusSeenByClient' => false]);
        } else {
            $newConfirmations = $bookingRepo->count(['client' => $user, 'status' => 'confirmed', 'isStatusSeenByClient' => false]);
            $newFeedbacks = (int)$em->getRepository(SessionHistory::class)->createQueryBuilder('sh')
                ->select('count(sh.id)')->where('sh.client = :user AND sh.isReadByClient = false')
                ->setParameter('user', $user)->getQuery()->getSingleScalarResult();
            $total = $newConfirmations + $newFeedbacks;
        }

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

        // 1. Pour les Bookings (Demandes, Confirmations, Annulations)
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

        // 2. Pour les SessionHistory (Feedbacks / Bilans) - Uniquement pour les clients
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

        if (empty($items)) {
            return $this->json(['success' => false, 'message' => 'Aucun élément sélectionné'], 400);
        }

        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            $type = $item['type'] ?? '';

            // 1. Pour les notifications basées sur les Bookings
            if (in_array($type, ['confirmation', 'request', 'alert'])) {
                $booking = $em->getRepository(Booking::class)->find($id);
                if ($booking) {
                    // On ne supprime pas la ligne en BDD (car le match existe toujours)
                    // On le masque simplement de la vue "notifications"
                    $booking->setIsStatusSeenByClient(true);
                }
            }
            // 2. Pour les notifications de Bilans (SessionHistory)
            elseif ($type === 'feedback') {
                $history = $em->getRepository(SessionHistory::class)->find($id);
                if ($history) {
                    $history->setIsReadByClient(true);
                }
            }
        }

        // On flush une seule fois à la fin pour optimiser les performances
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => count($items) . ' notifications marquées comme lues'
        ]);
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
