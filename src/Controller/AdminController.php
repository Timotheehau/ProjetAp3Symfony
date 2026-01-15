<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ProfileRepository;
use App\Repository\BookingRepository;
use App\Repository\ReviewRepository;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        UserRepository $userRepository,
        ProfileRepository $profileRepository,
        BookingRepository $bookingRepository,
        ReviewRepository $reviewRepository,
        DonationRepository $donationRepository
    ): JsonResponse {
        $totalUsers = $userRepository->count([]);
        $totalProfiles = $profileRepository->count(['isVerified' => true]);
        $pendingProfiles = $profileRepository->count(['isVerified' => false]);
        $totalBookings = $bookingRepository->count([]);
        $confirmedBookings = $bookingRepository->count(['status' => 'confirmed']);
        $completedBookings = $bookingRepository->count(['status' => 'completed']);
        $totalReviews = $reviewRepository->count([]);
        $totalDonations = $donationRepository->count(['status' => 'completed']);

        $completedDonations = $donationRepository->findBy(['status' => 'completed']);
        $totalDonationAmount = array_reduce($completedDonations, function($sum, $donation) {
            return $sum + floatval($donation->getAmount());
        }, 0);

        return $this->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'professionals' => $userRepository->count(['userType' => 'professional']),
                    'particulars' => $userRepository->count(['userType' => 'particular']),
                ],
                'profiles' => [
                    'total' => $totalProfiles,
                    'pending' => $pendingProfiles,
                    'verified' => $totalProfiles,
                ],
                'bookings' => [
                    'total' => $totalBookings,
                    'confirmed' => $confirmedBookings,
                    'completed' => $completedBookings,
                    'pending' => $bookingRepository->count(['status' => 'pending']),
                ],
                'reviews' => [
                    'total' => $totalReviews,
                ],
                'donations' => [
                    'total' => $totalDonations,
                    'totalAmount' => $totalDonationAmount,
                ],
            ]
        ]);
    }

    #[Route('/profiles/pending', name: 'admin_profiles_pending', methods: ['GET'])]
    public function pendingProfiles(ProfileRepository $profileRepository): JsonResponse
    {
        $profiles = $profileRepository->findBy(['isVerified' => false], ['createdAt' => 'DESC']);
        
        $data = array_map(function($profile) {
            return [
                'id' => $profile->getId(),
                'specialty' => $profile->getSpecialty(),
                'level' => $profile->getLevel(),
                'yearsOfExperience' => $profile->getYearsOfExperience(),
                'city' => $profile->getCity(),
                'certifications' => $profile->getCertifications(),
                'diplomas' => $profile->getDiplomas(),
                'createdAt' => $profile->getCreatedAt()->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $profile->getUser()->getId(),
                    'email' => $profile->getUser()->getEmail(),
                    'firstName' => $profile->getUser()->getFirstName(),
                    'lastName' => $profile->getUser()->getLastName(),
                ],
            ];
        }, $profiles);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/profiles/{id}/verify', name: 'admin_profiles_verify', methods: ['POST'])]
    public function verifyProfile(
        int $id,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $profile = $profileRepository->find($id);
        
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $profile->setIsVerified(true);
        $profile->setVerifiedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil vérifié avec succès'
        ]);
    }

    #[Route('/profiles/{id}/reject', name: 'admin_profiles_reject', methods: ['POST'])]
    public function rejectProfile(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $profile = $profileRepository->find($id);
        
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'Non spécifié';

        $profile->setIsActive(false);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil rejeté',
            'reason' => $reason
        ]);
    }

    #[Route('/users', name: 'admin_users_list', methods: ['GET'])]
    public function usersList(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findBy([], ['createdAt' => 'DESC'], 50);
        
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

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/users/{id}/deactivate', name: 'admin_users_deactivate', methods: ['POST'])]
    public function deactivateUser(
        int $id,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): JsonResponse {
        $user = $userRepository->find($id);
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $user->setIsActive(false);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur désactivé'
        ]);
    }

    #[Route('/bookings/recent', name: 'admin_bookings_recent', methods: ['GET'])]
    public function recentBookings(BookingRepository $bookingRepository): JsonResponse
    {
        $bookings = $bookingRepository->findBy([], ['createdAt' => 'DESC'], 20);
        
        $data = array_map(function($booking) {
            return [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
                'startTime' => $booking->getStartTime()->format('Y-m-d H:i'),
                'totalPrice' => $booking->getTotalPrice(),
                'client' => [
                    'firstName' => $booking->getClient()->getFirstName(),
                    'lastName' => $booking->getClient()->getLastName(),
                ],
                'professional' => [
                    'firstName' => $booking->getProfile()->getUser()->getFirstName(),
                    'lastName' => $booking->getProfile()->getUser()->getLastName(),
                ],
                'createdAt' => $booking->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $bookings);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/stats/revenue', name: 'admin_stats_revenue', methods: ['GET'])]
    public function revenueStats(BookingRepository $bookingRepository): JsonResponse
    {
        $completedBookings = $bookingRepository->findBy(['status' => 'completed']);
        
        $totalRevenue = array_reduce($completedBookings, function($sum, $booking) {
            return $sum + floatval($booking->getTotalPrice());
        }, 0);

        $currentMonth = date('Y-m');
        $monthlyBookings = array_filter($completedBookings, function($booking) use ($currentMonth) {
            return $booking->getCreatedAt()->format('Y-m') === $currentMonth;
        });

        $monthlyRevenue = array_reduce($monthlyBookings, function($sum, $booking) {
            return $sum + floatval($booking->getTotalPrice());
        }, 0);

        return $this->json([
            'success' => true,
            'data' => [
                'totalRevenue' => $totalRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'totalBookings' => count($completedBookings),
                'monthlyBookings' => count($monthlyBookings),
                'currency' => 'EUR'
            ]
        ]);
    }
}
