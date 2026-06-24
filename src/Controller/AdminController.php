<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Notification;
use App\Repository\UserRepository;
use App\Repository\BookingRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[OA\Tag(name: 'Admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    /**
     * Liste tous les utilisateurs inscrits
     */
    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'userType' => $user->getUserType(),
                'isActive' => $user->isActive(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $users);

        return $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * Liste des coachs en attente de validation (isVerified = false)
     */
    #[Route('/pending-coaches', name: 'admin_pending_coaches', methods: ['GET'])]
    public function pendingCoaches(UserRepository $userRepository): JsonResponse
    {
        $pendingCoaches = $userRepository->createQueryBuilder('u')
            ->join('u.profile', 'p')
            ->where('u.userType = :type')
            ->andWhere('p.isVerified = :verified')
            ->setParameter('type', 'professional')
            ->setParameter('verified', false)
            ->getQuery()
            ->getResult();

        $data = array_map(function($user) {
            $profile = $user->getProfile();
            return [
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'city' => $profile->getCity(),
                'diplomas' => $profile->getDiplomas(),
                'certifications' => $profile->getCertifications(),
                'createdAt' => $user->getCreatedAt()->format('d/m/Y H:i'),
            ];
        }, $pendingCoaches);

        return $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * Approuver ou Refuser un coach
     */
    #[Route('/coaches/{id}/verify', name: 'admin_coach_verify', methods: ['POST'])]
    public function verifyCoach(int $id, UserRepository $userRepo, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $userRepo->find($id);
        if (!$user || $user->getUserType() !== 'professional' || !$user->getProfile()) {
            return $this->json(['success' => false, 'message' => 'Coach non trouvé'], 404);
        }

        $content = json_decode($request->getContent(), true);
        $action = $content['action'] ?? 'approve';

        if ($action === 'approve') {
            $user->getProfile()->setIsVerified(true);
            // Ici, on ne peut pas créer de notification "virtuelle" car
            // le coach verra juste son écran se débloquer.
        } else {
            $message = 'Demande rejetée.';
        }

        $em->flush();
        return $this->json(['success' => true]);
    }

    /**
     * Statistiques globales du Dashboard
     */
    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(UserRepository $userRepository, BookingRepository $bookingRepository): JsonResponse
    {
        $sevenDaysAgo = new \DateTime('-7 days');
        return $this->json([
            'success' => true,
            'data' => [
                'totalUsers' => $userRepository->countAll(),
                'totalBookings' => $bookingRepository->countAll(),
                'growth' => [
                    'newUsers' => $userRepository->countNewUsersSince($sevenDaysAgo),
                    'newBookings' => $bookingRepository->countNewBookingsSince($sevenDaysAgo),
                ],
                'updatedAt' => new \DateTime()->format('H:i:s')
            ]
        ]);
    }

    /**
     * Bannir ou Réactiver un utilisateur
     */
    #[Route('/users/{id}/toggle-status', name: 'admin_user_toggle', methods: ['PATCH'])]
    public function toggleUserStatus(int $id, UserRepository $userRepository, EntityManagerInterface $em): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        return $this->json([
            'success' => true,
            'isActive' => $user->isActive(),
            'message' => $user->isActive() ? 'Compte activé' : 'Compte désactivé'
        ]);
    }

    /**
     * Liste toutes les réservations de la plateforme
     */
    #[Route('/bookings', name: 'admin_bookings_list', methods: ['GET'])]
    public function listAllBookings(BookingRepository $bookingRepository): JsonResponse
    {
        $bookings = $bookingRepository->findAll();

        $data = array_map(function($booking) {
            return [
                'id' => $booking->getId(),
                'client' => $booking->getClient()->getFirstName() . ' ' . $booking->getClient()->getLastName(),
                'coach' => $booking->getProfile()->getUser()->getFirstName() . ' ' . $booking->getProfile()->getUser()->getLastName(),
                'status' => $booking->getStatus(),
                'startTime' => $booking->getStartTime()->format('d/m/Y H:i'),
            ];
        }, $bookings);

        return $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * Nombre de réservations par profil et par ville
     */
    #[Route('/bookings/stats-by-profile', name: 'admin_bookings_stats_by_profile', methods: ['GET'])]
    public function bookingsStatsByProfile(ProfileRepository $profileRepository): JsonResponse
    {
        $stats = $profileRepository->countBookingsByProfileAndCity();

        $data = array_map(function($row) {
            return [
                'id_profile' => (int) $row['id_profile'],
                'ville' => $row['ville'],
                'nb_reservations' => (int) $row['nb_reservations'],
            ];
        }, $stats);

        return $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * Liste des profils n'ayant jamais reçu de réservation
     */
    #[Route('/profiles/never-booked', name: 'admin_profiles_never_booked', methods: ['GET'])]
    public function neverBookedProfiles(ProfileRepository $profileRepository): JsonResponse
    {
        $profiles = $profileRepository->findNeverBooked();

        $data = array_map(function($profile) {
            return [
                'id_profile' => $profile->getId(),
                'ville' => $profile->getCity(),
                'firstName' => $profile->getUser()->getFirstName(),
                'lastName' => $profile->getUser()->getLastName(),
            ];
        }, $profiles);

        return $this->json(['success' => true, 'data' => $data]);
    }
}
