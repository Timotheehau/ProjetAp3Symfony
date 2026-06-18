<?php

namespace App\Controller;

use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        try {
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
                    'city' => $profile->getCity(),
                    'averageRating' => $profile->getAverageRating(),
                    'totalReviews' => $profile->getTotalReviews(),
                    'isVerified' => $profile->getIsVerified(), // ← CORRECTION ICI
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

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    #[Route('/search', name: 'profiles_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $sportId = $request->query->get('sportId');
            // On force en float. Si c'est vide ou invalide, ça devient 0.0
            $userLat = $request->query->get('lat') !== "" ? (float)$request->query->get('lat') : null;
            $userLon = $request->query->get('lon') !== "" ? (float)$request->query->get('lon') : null;

            $qb = $em->createQueryBuilder();

            // On définit proprement les alias
            $qb->select('p', 'u')
                ->from('App\Entity\Profile', 'p')
                ->innerJoin('p.user', 'u')
                ->where('u.isActive = :active')
                ->setParameter('active', true);

            // Filtre par Sport : On n'ajoute la jointure QUE si nécessaire
            if (!empty($sportId)) {
                $qb->innerJoin('p.sports', 's') // Jointure seulement si on filtre
                ->andWhere('s.id = :sportId')
                    ->setParameter('sportId', $sportId);
            }

            $profiles = $qb->getQuery()->getResult();
            $results = [];

            foreach ($profiles as $profile) {
                $distance = null;

                // On ne calcule la distance QUE si on a des coordonnées valides (pas null et pas 0)
                if ($userLat && $userLon && $profile->getLatitude() && $profile->getLongitude()) {
                    $distance = $this->calculateDistance(
                        $userLat, $userLon,
                        (float)$profile->getLatitude(), (float)$profile->getLongitude()
                    );
                }

                $results[] = [
                    'id' => $profile->getId(),
                    'specialty' => $profile->getSpecialty(),
                    'city' => $profile->getCity(),
                    'bio' => $profile->getBio(),
                    'distance' => $distance !== null ? round($distance, 1) : null,
                    'user' => [
                        'firstName' => $profile->getUser()->getFirstName(),
                        'lastName' => $profile->getUser()->getLastName(),
                    ],
                ];
            }

            // Tri (Seulement si on a une distance sur laquelle trier)
            if ($userLat && $userLon) {
                usort($results, function($a, $b) {
                    if ($a['distance'] === $b['distance']) return 0;
                    if ($a['distance'] === null) return 1;
                    if ($b['distance'] === null) return -1;
                    return ($a['distance'] < $b['distance']) ? -1 : 1;
                });
            }

            return $this->json(['success' => true, 'data' => $results]);

        } catch (\Exception $e) {
            // C'EST ICI QUE TU VERRAS L'ERREUR DANS TES LOGS SI CA RE-PLANTE
            return $this->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
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
                'city' => $profile->getCity(),
                'address' => $profile->getAddress(),
                'latitude' => $profile->getLatitude(),
                'longitude' => $profile->getLongitude(),
                'certifications' => $profile->getCertifications(),
                'diplomas' => $profile->getDiplomas(),
                'averageRating' => $profile->getAverageRating(),
                'totalReviews' => $profile->getTotalReviews(),
                'isVerified' => $profile->getIsVerified(),
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
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en kilomètres

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
