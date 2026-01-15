<?php

namespace App\Controller;

use App\Repository\ProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/profiles')]
#[OA\Tag(name: 'Profiles')]
class ProfilesController extends AbstractController
{
    #[Route('', name: 'profiles_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profiles',
        summary: 'Liste tous les profils professionnels (accès public)',
        tags: ['Profiles']
    )]
    #[OA\Parameter(
        name: 'sportId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par sport'
    )]
    #[OA\Parameter(
        name: 'city',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
        description: 'Filtrer par ville'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des profils',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'specialty', type: 'string'),
                            new OA\Property(property: 'level', type: 'string'),
                            new OA\Property(property: 'hourlyRate', type: 'number'),
                            new OA\Property(property: 'city', type: 'string'),
                            new OA\Property(property: 'averageRating', type: 'number'),
                            new OA\Property(property: 'totalReviews', type: 'integer')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, ProfileRepository $profileRepository): JsonResponse
    {
        $sportId = $request->query->get('sportId');
        $city = $request->query->get('city');

        $queryBuilder = $profileRepository->createQueryBuilder('p');

        if ($sportId) {
            $queryBuilder
                ->join('p.sports', 's')
                ->andWhere('s.id = :sportId')
                ->setParameter('sportId', $sportId);
        }

        if ($city) {
            $queryBuilder
                ->andWhere('p.city = :city')
                ->setParameter('city', $city);
        }

        $profiles = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($profile) {
            return [
                'id' => $profile->getId(),
                'specialty' => $profile->getSpecialty(),
                'level' => $profile->getLevel(),
                'bio' => $profile->getBio(),
                'yearsOfExperience' => $profile->getYearsOfExperience(),
                'hourlyRate' => $profile->getHourlyRate(),
                'city' => $profile->getCity(),
                'averageRating' => $profile->getAverageRating(),
                'totalReviews' => $profile->getTotalReviews(),
                'isVerified' => $profile->isVerified(),
                'user' => [
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

    #[Route('/{id}', name: 'profiles_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profiles/{id}',
        summary: 'Affiche un profil professionnel spécifique',
        tags: ['Profiles']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 200, description: 'Détails du profil')]
    #[OA\Response(response: 404, description: 'Profil non trouvé')]
    public function show(int $id, ProfileRepository $profileRepository): JsonResponse
    {
        $profile = $profileRepository->find($id);
        
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $profile->getId(),
                'specialty' => $profile->getSpecialty(),
                'level' => $profile->getLevel(),
                'bio' => $profile->getBio(),
                'yearsOfExperience' => $profile->getYearsOfExperience(),
                'hourlyRate' => $profile->getHourlyRate(),
                'city' => $profile->getCity(),
                'address' => $profile->getAddress(),
                'latitude' => $profile->getLatitude(),
                'longitude' => $profile->getLongitude(),
                'certifications' => $profile->getCertifications(),
                'diplomas' => $profile->getDiplomas(),
                'averageRating' => $profile->getAverageRating(),
                'totalReviews' => $profile->getTotalReviews(),
                'isVerified' => $profile->isVerified(),
                'user' => [
                    'id' => $profile->getUser()->getId(),
                    'firstName' => $profile->getUser()->getFirstName(),
                    'lastName' => $profile->getUser()->getLastName(),
                    'email' => $profile->getUser()->getEmail(),
                    'phone' => $profile->getUser()->getPhone(),
                ],
            ]
        ]);
    }
}
