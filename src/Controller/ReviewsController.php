<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\BookingRepository;
use App\Repository\ProfileRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/reviews')]
#[OA\Tag(name: 'Reviews')]
class ReviewsController extends AbstractController
{
    #[Route('', name: 'reviews_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/reviews',
        summary: 'Liste tous les avis (accès public)',
        tags: ['Reviews']
    )]
    #[OA\Parameter(
        name: 'profileId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par profil professionnel'
    )]
    #[OA\Parameter(
        name: 'authorId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par auteur'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des avis',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'rating', type: 'integer'),
                            new OA\Property(property: 'comment', type: 'string'),
                            new OA\Property(property: 'isVerified', type: 'boolean'),
                            new OA\Property(property: 'likes', type: 'integer'),
                            new OA\Property(property: 'createdAt', type: 'string')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, ReviewRepository $reviewRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        $authorId = $request->query->get('authorId');

        $queryBuilder = $reviewRepository->createQueryBuilder('r');

        if ($profileId) {
            $queryBuilder
                ->andWhere('r.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }

        if ($authorId) {
            $queryBuilder
                ->andWhere('r.author = :authorId')
                ->setParameter('authorId', $authorId);
        }

        $queryBuilder->orderBy('r.createdAt', 'DESC');
        $reviews = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($review) {
            return [
                'id' => $review->getId(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'isVerified' => $review->isVerified(),
                'response' => $review->getResponse(),
                'likes' => $review->getLikes(),
                'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
                'author' => [
                    'id' => $review->getAuthor()->getId(),
                    'firstName' => $review->getAuthor()->getFirstName(),
                    'lastName' => $review->getAuthor()->getLastName(),
                ],
                'profile' => [
                    'id' => $review->getProfile()->getId(),
                    'specialty' => $review->getProfile()->getSpecialty(),
                ],
            ];
        }, $reviews);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'reviews_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/reviews/{id}',
        summary: 'Affiche un avis spécifique',
        tags: ['Reviews']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 200, description: 'Détails de l\'avis')]
    #[OA\Response(response: 404, description: 'Avis non trouvé')]
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
                'isVerified' => $review->isVerified(),
                'response' => $review->getResponse(),
                'likes' => $review->getLikes(),
                'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
                'author' => [
                    'id' => $review->getAuthor()->getId(),
                    'firstName' => $review->getAuthor()->getFirstName(),
                    'lastName' => $review->getAuthor()->getLastName(),
                ],
                'profile' => [
                    'id' => $review->getProfile()->getId(),
                    'specialty' => $review->getProfile()->getSpecialty(),
                ],
            ]
        ]);
    }

    #[Route('', name: 'reviews_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/reviews',
        summary: 'Créer un nouvel avis',
        security: [['Bearer' => []]],
        tags: ['Reviews']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['profileId', 'rating'],
            properties: [
                new OA\Property(property: 'profileId', type: 'integer'),
                new OA\Property(property: 'rating', type: 'integer', minimum: 1, maximum: 5),
                new OA\Property(property: 'comment', type: 'string'),
                new OA\Property(property: 'bookingId', type: 'integer', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Avis créé avec succès')]
    #[OA\Response(response: 400, description: 'Données invalides')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepo,
        BookingRepository $bookingRepo,
        ReviewRepository $reviewRepo // AJOUTE CETTE REPO
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['bookingId'])) {
            $existingReview = $reviewRepo->findOneBy([
                'booking' => $data['bookingId']
            ]);

            if ($existingReview) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tu as déjà noté ce match, champion !'
                ], 400);
            }
        }
        // Validation de base
        if (!isset($data['profileId'], $data['rating'])) {
            return $this->json(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        $profile = $profileRepo->find($data['profileId']);
        if (!$profile) {
            return $this->json(['success' => false, 'message' => 'Profil non trouvé'], 404);
        }

        $review = new Review();
        $review->setAuthor($user);
        $review->setProfile($profile);
        $review->setRating((int)$data['rating']);
        $review->setComment($data['comment'] ?? null);

        // Logique de vérification par Booking
        if (isset($data['bookingId'])) {
            $booking = $bookingRepo->find($data['bookingId']);
            // On vérifie que le booking appartient bien à l'auteur
            if ($booking && $booking->getClient() === $user) {
                $review->setBooking($booking);
                $review->setIsVerified(true);
            }
        }

        $em->persist($review);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Avis enregistré !',
            'data' => ['id' => $review->getId()]
        ], Response::HTTP_CREATED);
    }
}
