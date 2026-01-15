<?php

namespace App\Controller;

use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/availabilities')]
class AvailabilitiesController extends AbstractController
{
    #[Route('', name: 'availabilities_list', methods: ['GET'])]
    public function list(Request $request, AvailabilityRepository $availabilityRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        
        $queryBuilder = $availabilityRepository->createQueryBuilder('a');
        
        if ($profileId) {
            $queryBuilder->andWhere('a.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }
        
        $queryBuilder->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC');
        
        $availabilities = $queryBuilder->getQuery()->getResult();
        
        $data = array_map(function($availability) {
            return [
                'id' => $availability->getId(),
                'dayOfWeek' => $availability->getDayOfWeek(),
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
                'isRecurring' => $availability->isRecurring(),
                'specificDate' => $availability->getSpecificDate()?->format('Y-m-d'),
                'isAvailable' => $availability->isAvailable(),
                'profile' => [
                    'id' => $availability->getProfile()->getId(),
                    'user' => [
                        'firstName' => $availability->getProfile()->getUser()->getFirstName(),
                        'lastName' => $availability->getProfile()->getUser()->getLastName(),
                    ],
                ],
            ];
        }, $availabilities);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'availabilities_show', methods: ['GET'])]
    public function show(int $id, AvailabilityRepository $availabilityRepository): JsonResponse
    {
        $availability = $availabilityRepository->find($id);
        
        if (!$availability) {
            return $this->json([
                'success' => false,
                'message' => 'Disponibilité non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $availability->getId(),
                'dayOfWeek' => $availability->getDayOfWeek(),
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
                'isRecurring' => $availability->isRecurring(),
                'specificDate' => $availability->getSpecificDate()?->format('Y-m-d'),
                'isAvailable' => $availability->isAvailable(),
            ]
        ]);
    }

    #[Route('', name: 'availabilities_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $profile = $profileRepository->find($data['profileId'] ?? null);
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_BAD_REQUEST);
        }

        $availability = new Availability();
        $availability->setProfile($profile);
        $availability->setDayOfWeek($data['dayOfWeek'] ?? null);
        $availability->setStartTime(new \DateTime($data['startTime']));
        $availability->setEndTime(new \DateTime($data['endTime']));
        $availability->setIsRecurring($data['isRecurring'] ?? true);
        $availability->setIsAvailable($data['isAvailable'] ?? true);
        
        if (isset($data['specificDate'])) {
            $availability->setSpecificDate(new \DateTime($data['specificDate']));
        }

        $em->persist($availability);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Disponibilité créée avec succès',
            'data' => ['id' => $availability->getId()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'availabilities_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AvailabilityRepository $availabilityRepository
    ): JsonResponse {
        $availability = $availabilityRepository->find($id);
        
        if (!$availability) {
            return $this->json([
                'success' => false,
                'message' => 'Disponibilité non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['dayOfWeek'])) $availability->setDayOfWeek($data['dayOfWeek']);
        if (isset($data['startTime'])) $availability->setStartTime(new \DateTime($data['startTime']));
        if (isset($data['endTime'])) $availability->setEndTime(new \DateTime($data['endTime']));
        if (isset($data['isRecurring'])) $availability->setIsRecurring($data['isRecurring']);
        if (isset($data['isAvailable'])) $availability->setIsAvailable($data['isAvailable']);
        
        if (isset($data['specificDate'])) {
            $availability->setSpecificDate(new \DateTime($data['specificDate']));
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Disponibilité mise à jour avec succès'
        ]);
    }

    #[Route('/{id}', name: 'availabilities_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        AvailabilityRepository $availabilityRepository
    ): JsonResponse {
        $availability = $availabilityRepository->find($id);
        
        if (!$availability) {
            return $this->json([
                'success' => false,
                'message' => 'Disponibilité non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $em->remove($availability);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Disponibilité supprimée avec succès'
        ]);
    }

    #[Route('/profile/{profileId}/slots', name: 'availabilities_slots', methods: ['GET'])]
    public function getAvailableSlots(
        int $profileId,
        Request $request,
        ProfileRepository $profileRepository
    ): JsonResponse {
        $profile = $profileRepository->find($profileId);
        
        if (!$profile) {
            return $this->json([
                'success' => false,
                'message' => 'Profil non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $date = $request->query->get('date', date('Y-m-d'));
        $dayOfWeek = (int)date('w', strtotime($date));

        $availabilities = $profile->getAvailabilities()->filter(function($availability) use ($dayOfWeek, $date) {
            if ($availability->isRecurring() && $availability->getDayOfWeek() === $dayOfWeek) {
                return $availability->isAvailable();
            }
            if (!$availability->isRecurring() && $availability->getSpecificDate()) {
                return $availability->getSpecificDate()->format('Y-m-d') === $date && $availability->isAvailable();
            }
            return false;
        });

        $slots = array_map(function($availability) {
            return [
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
            ];
        }, $availabilities->toArray());

        return $this->json([
            'success' => true,
            'date' => $date,
            'slots' => array_values($slots)
        ]);
    }
}
