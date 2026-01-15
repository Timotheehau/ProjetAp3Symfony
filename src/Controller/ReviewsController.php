<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\ReviewLike;
use App\Repository\ReviewRepository;
use App\Repository\ReviewLikeRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reviews')]
class ReviewsController extends AbstractController
{
    #[Route('', name: 'reviews_list', methods: ['GET'])]
    public function list(Request $request, ReviewRepository $reviewRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        $authorId = $request->query->get('authorId');
        
        $queryBuilder = $reviewRepository->createQueryBuilder('r');
        
        if ($profileId) {
            $queryBuilder->andWhere('r.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }
        
        if ($authorId) {
            $queryBuilder->andWhere('r.author = :authorId')
                ->setParameter('authorId', $authorId);
        }
        
        $queryBuilder->orderBy('r.createdAt', 'DESC');
        $reviews = $queryBuilder->getQuery()->getResult();
        
        $data = array_map(function($review) {
            return [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'response' => $review->getResponse(),
                'likes' => $review->getLikes(),
                'isVerified' => $review->isVerified(),
                'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
                'author' => [
                    'id' => $review->getAuthor()->getId(),
                    'firstName' => $review->getAuthor()->getFirstName(),
                    'lastName' => $review->getAuthor()->getLastName(),
                ],
                'profile' => [
                    'id' => $review->getProfile()->getId(),
                    'user' => [
                        'firstName' => $review->getProfile()->getUser()->getFirstName(),
                        'lastName' => $review->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ];
        }, $reviews);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'reviews_show', methods: ['GET'])]
    public function show(int $id, ReviewRepository $reviewRepository): JsonResponse
    {
        $review = $reviewRepository->find($id);
        
        if (!$review) {
            return $this->json([
                'success' => false,
                'message' => 'Avis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'response' => $review->getResponse(),
                'likes' => $review->getLikes(),
                'isVerified' => $review->isVerified(),
                'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
                'author' => [
                    'firstName' => $review->getAuthor()->getFirstName(),
                    'lastName' => $review->getAuthor()->getLastName(),
                ],
            ]
        ]);
    }

    #[Route('', name: 'reviews_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository,
        UserRepository $userRepository,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $profile = $profileRepository->find($data['profileId'] ?? null);
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $author = $userRepository->find($data['authorId'] ?? null);
        if (!$author) {
            return $this->json([
                'success' => false,
                'message' => 'Auteur non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $review = new Review();
        $review->setProfile($profile);
        $review->setAuthor($author);
        $review->setRating($data['rating']);
        $review->setComment($data['comment'] ?? null);
        $review->setIsVerified(false);
        
        if (isset($data['bookingId'])) {
            $booking = $bookingRepository->find($data['bookingId']);
            if ($booking && $booking->getStatus() === 'completed') {
                $review->setBooking($booking);
                $review->setIsVerified(true);
            }
        }

        $em->persist($review);
        $em->flush();

        // Recalculer la note moyenne du profil
        $profile->recalculateRating();
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Avis créé avec succès',
            'data' => ['id' => $review->getId()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/response', name: 'reviews_respond', methods: ['POST'])]
    public function respond(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository
    ): JsonResponse {
        $review = $reviewRepository->find($id);
        
        if (!$review) {
            return $this->json([
                'success' => false,
                'message' => 'Avis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $review->setResponse($data['response']);
        $review->setUpdatedAt(new \DateTimeImmutable());
        
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Réponse ajoutée avec succès'
        ]);
    }

    #[Route('/{id}/like', name: 'reviews_like', methods: ['POST'])]
    public function like(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository,
        ReviewLikeRepository $reviewLikeRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $review = $reviewRepository->find($id);
        
        if (!$review) {
            return $this->json([
                'success' => false,
                'message' => 'Avis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $user = $userRepository->find($data['userId']);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si déjà liké
        $existingLike = $reviewLikeRepository->findOneBy([
            'review' => $review,
            'user' => $user
        ]);

        if ($existingLike) {
            return $this->json([
                'success' => false,
                'message' => 'Vous avez déjà liké cet avis'
            ], Response::HTTP_CONFLICT);
        }

        $like = new ReviewLike();
        $like->setReview($review);
        $like->setUser($user);
        
        $review->incrementLikes();

        $em->persist($like);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Like ajouté',
            'likes' => $review->getLikes()
        ]);
    }

    #[Route('/{id}/unlike', name: 'reviews_unlike', methods: ['POST'])]
    public function unlike(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository,
        ReviewLikeRepository $reviewLikeRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $review = $reviewRepository->find($id);
        
        if (!$review) {
            return $this->json([
                'success' => false,
                'message' => 'Avis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $user = $userRepository->find($data['userId']);

        $like = $reviewLikeRepository->findOneBy([
            'review' => $review,
            'user' => $user
        ]);

        if (!$like) {
            return $this->json([
                'success' => false,
                'message' => 'Like non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $review->decrementLikes();
        $em->remove($like);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Like retiré',
            'likes' => $review->getLikes()
        ]);
    }

    #[Route('/{id}', name: 'reviews_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        ReviewRepository $reviewRepository
    ): JsonResponse {
        $review = $reviewRepository->find($id);
        
        if (!$review) {
            return $this->json([
                'success' => false,
                'message' => 'Avis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $profile = $review->getProfile();
        $em->remove($review);
        $em->flush();

        // Recalculer la note moyenne
        $profile->recalculateRating();
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Avis supprimé avec succès'
        ]);
    }
}
