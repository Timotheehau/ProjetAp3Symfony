<?php

namespace App\Controller;

use App\Entity\Availability;
use App\Repository\AvailabilityRepository;
use App\Repository\ProfileRepository;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/availabilities')]
#[OA\Tag(name: 'Availabilities')]
class AvailabilitiesController extends AbstractController
{
    #[Route('', name: 'availabilities_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/availabilities',
        summary: 'Liste toutes les disponibilités avec infos du sport',
        security: [['Bearer' => []]],
        tags: ['Availabilities']
    )]
    #[OA\Parameter(
        name: 'profileId',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
        description: 'Filtrer par profil professionnel'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des disponibilités',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'dayOfWeek', type: 'integer'),
                            new OA\Property(property: 'startTime', type: 'string'),
                            new OA\Property(property: 'endTime', type: 'string'),
                            new OA\Property(property: 'isRecurring', type: 'boolean'),
                            new OA\Property(property: 'isAvailable', type: 'boolean'),
                            new OA\Property(
                                property: 'sport',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'icon', type: 'string')
                                ]
                            )
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(Request $request, AvailabilityRepository $availabilityRepository): JsonResponse
    {
        $profileId = $request->query->get('profileId');
        $queryBuilder = $availabilityRepository->createQueryBuilder('a');

        if ($profileId) {
            $queryBuilder
                ->andWhere('a.profile = :profileId')
                ->setParameter('profileId', $profileId);
        }

        $availabilities = $queryBuilder->getQuery()->getResult();

        $data = array_map(function($availability) {
            return [
                'id' => $availability->getId(),
                'dayOfWeek' => $availability->getDayOfWeek(),
                'startTime' => $availability->getStartTime()->format('H:i:s'),
                'endTime' => $availability->getEndTime()->format('H:i:s'),
                'isRecurring' => $availability->isRecurring(),
                'specificDate' => $availability->getSpecificDate()?->format('Y-m-d'),
                'isAvailable' => $availability->isAvailable(),
                'sport' => $availability->getSport() ? [
                    'id' => $availability->getSport()->getId(),
                    'name' => $availability->getSport()->getName(),
                    'icon' => $availability->getSport()->getIcon(),
                ] : null,
            ];
        }, $availabilities);

        return $this->json(['success' => true, 'data' => $data]);
    }

    #[Route('', name: 'availabilities_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/availabilities',
        summary: 'Créer une nouvelle disponibilité liée à un sport',
        security: [['Bearer' => []]],
        tags: ['Availabilities']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['startTime', 'endTime', 'sportId'],
            properties: [
                new OA\Property(property: 'sportId', type: 'integer', example: 1),
                new OA\Property(property: 'dayOfWeek', type: 'integer', minimum: 0, maximum: 6, nullable: true),
                new OA\Property(property: 'startTime', type: 'string', format: 'time', example: '09:00'),
                new OA\Property(property: 'endTime', type: 'string', format: 'time', example: '10:00'),
                new OA\Property(property: 'isRecurring', type: 'boolean', example: true),
                new OA\Property(property: 'specificDate', type: 'string', format: 'date', nullable: true)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Disponibilité créée avec succès')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SportRepository $sportRepository
    ): JsonResponse {
        try {
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true);

            $profile = $user->getProfile();
            if (!$profile) {
                return $this->json(['success' => false, 'message' => 'Profil coach manquant'], 403);
            }

            $availability = new Availability();
            $availability->setProfile($profile);

            // Liaison du Sport
            if (!isset($data['sportId'])) {
                return $this->json(['success' => false, 'message' => 'Le sport est obligatoire'], 400);
            }

            $sport = $sportRepository->find($data['sportId']);
            if (!$sport) {
                return $this->json(['success' => false, 'message' => 'Sport introuvable'], 404);
            }
            $availability->setSport($sport);

            if (isset($data['dayOfWeek'])) $availability->setDayOfWeek($data['dayOfWeek']);
            if (isset($data['specificDate'])) $availability->setSpecificDate(new \DateTime($data['specificDate']));

            $availability->setStartTime(new \DateTime($data['startTime']));
            $availability->setEndTime(new \DateTime($data['endTime']));
            $availability->setIsRecurring($data['isRecurring'] ?? true);
            $availability->setIsAvailable(true);

            if ($availability->getEndTime() <= $availability->getStartTime()) {
                return $this->json(['success' => false, 'message' => 'L\'heure de fin doit être supérieure'], 400);
            }

            $em->persist($availability);
            $em->flush();

            return $this->json([
                'success' => true,
                'id' => $availability->getId(),
                'message' => 'Disponibilité ajoutée avec succès'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'availabilities_delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/availabilities/{id}', summary: 'Supprimer une disponibilité', tags: ['Availabilities'])]
    public function delete(Availability $availability, EntityManagerInterface $em): JsonResponse
    {
        if ($availability->getProfile() !== $this->getUser()->getProfile()) {
            return $this->json(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $em->remove($availability);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Disponibilité supprimée']);
    }
}
