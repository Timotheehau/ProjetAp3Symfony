<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Repository\DonationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/donations')]
class DonationsController extends AbstractController
{
    #[Route('', name: 'donations_list', methods: ['GET'])]
    public function list(Request $request, DonationRepository $donationRepository): JsonResponse
    {
        $userId = $request->query->get('userId');
        $status = $request->query->get('status');
        
        $queryBuilder = $donationRepository->createQueryBuilder('d');
        
        if ($userId) {
            $queryBuilder->andWhere('d.user = :userId')
                ->setParameter('userId', $userId);
        }
        
        if ($status) {
            $queryBuilder->andWhere('d.status = :status')
                ->setParameter('status', $status);
        }
        
        $queryBuilder->orderBy('d.createdAt', 'DESC');
        $donations = $queryBuilder->getQuery()->getResult();
        
        $data = array_map(function($donation) {
            return [
                'id' => $donation->getId(),
                'amount' => $donation->getAmount(),
                'currency' => $donation->getCurrency(),
                'message' => $donation->getMessage(),
                'isAnonymous' => $donation->isAnonymous(),
                'status' => $donation->getStatus(),
                'createdAt' => $donation->getCreatedAt()->format('Y-m-d H:i:s'),
                'user' => $donation->isAnonymous() ? null : [
                    'id' => $donation->getUser()->getId(),
                    'firstName' => $donation->getUser()->getFirstName(),
                    'lastName' => $donation->getUser()->getLastName(),
                ],
            ];
        }, $donations);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'donations_show', methods: ['GET'])]
    public function show(int $id, DonationRepository $donationRepository): JsonResponse
    {
        $donation = $donationRepository->find($id);
        
        if (!$donation) {
            return $this->json([
                'success' => false,
                'message' => 'Don non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $donation->getId(),
                'amount' => $donation->getAmount(),
                'currency' => $donation->getCurrency(),
                'message' => $donation->getMessage(),
                'isAnonymous' => $donation->isAnonymous(),
                'status' => $donation->getStatus(),
                'paymentMethod' => $donation->getPaymentMethod(),
                'transactionId' => $donation->getTransactionId(),
                'createdAt' => $donation->getCreatedAt()->format('Y-m-d H:i:s'),
                'processedAt' => $donation->getProcessedAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('', name: 'donations_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = $userRepository->find($data['userId'] ?? null);
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $donation = new Donation();
        $donation->setUser($user);
        $donation->setAmount($data['amount']);
        $donation->setCurrency($data['currency'] ?? 'EUR');
        $donation->setMessage($data['message'] ?? null);
        $donation->setIsAnonymous($data['isAnonymous'] ?? false);
        $donation->setStatus('pending');
        $donation->setPaymentMethod($data['paymentMethod'] ?? null);
        
        // Simuler un ID de transaction
        $donation->setTransactionId('TXN_' . uniqid());

        $em->persist($donation);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Don créé avec succès',
            'data' => [
                'id' => $donation->getId(),
                'transactionId' => $donation->getTransactionId(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/status', name: 'donations_update_status', methods: ['PATCH'])]
    public function updateStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        DonationRepository $donationRepository
    ): JsonResponse {
        $donation = $donationRepository->find($id);
        
        if (!$donation) {
            return $this->json([
                'success' => false,
                'message' => 'Don non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, ['pending', 'completed', 'failed', 'refunded'])) {
            return $this->json([
                'success' => false,
                'message' => 'Statut invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        $donation->setStatus($newStatus);

        if ($newStatus === 'completed') {
            $donation->setProcessedAt(new \DateTimeImmutable());
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Statut du don mis à jour'
        ]);
    }

    #[Route('/stats/total', name: 'donations_stats', methods: ['GET'])]
    public function stats(DonationRepository $donationRepository): JsonResponse
    {
        $completedDonations = $donationRepository->findBy(['status' => 'completed']);
        
        $total = array_reduce($completedDonations, function($sum, $donation) {
            return $sum + floatval($donation->getAmount());
        }, 0);

        return $this->json([
            'success' => true,
            'data' => [
                'totalAmount' => $total,
                'totalDonations' => count($completedDonations),
                'currency' => 'EUR'
            ]
        ]);
    }
}
