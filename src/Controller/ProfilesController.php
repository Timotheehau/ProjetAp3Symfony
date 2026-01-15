<?php

namespace App\Controller;

use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profiles')]
class ProfilesController extends AbstractController
{
    #[Route('', name: 'profiles_list', methods: ['GET'])]
    public function list(Request $request, ProfileRepository $profileRepository): JsonResponse
    {
        $sport = $request->query->get('sport');
        $specialty = $request->query->get('specialty');
        $level = $request->query->get('level');
        $city = $request->query->get('city');
        $minRating = $request->query->get('minRating');

        $queryBuilder = $profileRepository->createQueryBuilder('p')
            ->where('p.isVerified = :verified')
            ->andWhere('p.isActive = :active')
            ->setParameter('verified', true)
            ->setParameter('active', true);

        if ($sport) {
            $queryBuilder->join('p.sports', 's')
                ->andWhere('s.id = :sport')
                ->setParameter('sport', $sport);
        }

        if ($specialty) {
            $queryBuilder->andWhere('p.specialty = :specialty')
                ->setParameter('specialty', $specialty);
        }

        if ($level) {
            $queryBuilder->andWhere('p.level = :level')
                ->setParameter('level', $level);
        }

        if ($city) {
            $queryBuilder->andWhere('p.city LIKE :city')
                ->setParameter('city', '%' . $city . '%');
        }

        if ($minRating) {
            $queryBuilder->andWhere('p.averageRating >= :minRating')
                ->setParameter('minRating', $minRating);
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
                'isVerified' => $profile->getIsVerified(),
                'user' => [
                    'id' => $profile->getUser()->getId(),
                    'firstName' => $profile->getUser()->getFirstName(),
                    'lastName' => $profile->getUser()->getLastName(),
                ],
                'sports' => array_map(fn($sport) => [
                    'id' => $sport->getId(),
                    'name' => $sport->getName(),
                ], $profile->getSports()->toArray()),
            ];
        }, $profiles);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'profiles_show', methods: ['GET'])]
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
                'isVerified' => $profile->getIsVerified(),
                'isActive' => $profile->getIsActive(),
                'user' => [
                    'id' => $profile->getUser()->getId(),
                    'firstName' => $profile->getUser()->getFirstName(),
                    'lastName' => $profile->getUser()->getLastName(),
                    'email' => $profile->getUser()->getEmail(),
                    'phone' => $profile->getUser()->getPhone(),
                ],
                'sports' => array_map(fn($sport) => [
                    'id' => $sport->getId(),
                    'name' => $sport->getName(),
                ], $profile->getSports()->toArray()),
            ]
        ]);
    }

    #[Route('', name: 'profiles_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        SportRepository $sportRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = $userRepository->find($data['userId'] ?? null);
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $profile = new Profile();
        $profile->setUser($user);
        $profile->setSpecialty($data['specialty'] ?? null);
        $profile->setLevel($data['level'] ?? 'amateur');
        $profile->setBio($data['bio'] ?? null);
        $profile->setYearsOfExperience($data['yearsOfExperience'] ?? 0);
        $profile->setHourlyRate($data['hourlyRate'] ?? null);
        $profile->setCity($data['city'] ?? null);
        $profile->setAddress($data['address'] ?? null);
        $profile->setLatitude($data['latitude'] ?? null);
        $profile->setLongitude($data['longitude'] ?? null);
        $profile->setCertifications($data['certifications'] ?? null);
        $profile->setDiplomas($data['diplomas'] ?? null);
        $profile->setIsVerified(false);
        $profile->setIsActive(true);
        $profile->setCreatedAt(new \DateTimeImmutable());

        if (isset($data['sports']) && is_array($data['sports'])) {
            foreach ($data['sports'] as $sportId) {
                $sport = $sportRepository->find($sportId);
                if ($sport) {
                    $profile->addSport($sport);
                }
            }
        }

        $em->persist($profile);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil créé avec succès. En attente de vérification.',
            'data' => [
                'id' => $profile->getId(),
                'specialty' => $profile->getSpecialty(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'profiles_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository,
        SportRepository $sportRepository
    ): JsonResponse {
        $profile = $profileRepository->find($id);
        
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['specialty'])) $profile->setSpecialty($data['specialty']);
        if (isset($data['level'])) $profile->setLevel($data['level']);
        if (isset($data['bio'])) $profile->setBio($data['bio']);
        if (isset($data['yearsOfExperience'])) $profile->setYearsOfExperience($data['yearsOfExperience']);
        if (isset($data['hourlyRate'])) $profile->setHourlyRate($data['hourlyRate']);
        if (isset($data['city'])) $profile->setCity($data['city']);
        if (isset($data['address'])) $profile->setAddress($data['address']);

        if (isset($data['sports']) && is_array($data['sports'])) {
            foreach ($profile->getSports() as $sport) {
                $profile->removeSport($sport);
            }
            foreach ($data['sports'] as $sportId) {
                $sport = $sportRepository->find($sportId);
                if ($sport) {
                    $profile->addSport($sport);
                }
            }
        }

        $profile->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès'
        ]);
    }
}
