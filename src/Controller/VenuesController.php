<?php

namespace App\Controller;

use App\Entity\Venue;
use App\Repository\VenueRepository;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/venues')]
class VenuesController extends AbstractController
{
    #[Route('', name: 'venues_list', methods: ['GET'])]
    public function list(Request $request, VenueRepository $venueRepository): JsonResponse
    {
        $city = $request->query->get('city');
        $type = $request->query->get('type');
        $sportId = $request->query->get('sportId');
        
        $queryBuilder = $venueRepository->createQueryBuilder('v')
            ->where('v.isActive = :active')
            ->setParameter('active', true);
        
        if ($city) {
            $queryBuilder->andWhere('v.city LIKE :city')
                ->setParameter('city', '%' . $city . '%');
        }
        
        if ($type) {
            $queryBuilder->andWhere('v.type = :type')
                ->setParameter('type', $type);
        }
        
        if ($sportId) {
            $queryBuilder->andWhere('v.sport = :sportId')
                ->setParameter('sportId', $sportId);
        }
        
        $venues = $queryBuilder->getQuery()->getResult();
        
        $data = array_map(function($venue) {
            return [
                'id' => $venue->getId(),
                'name' => $venue->getName(),
                'type' => $venue->getType(),
                'address' => $venue->getAddress(),
                'city' => $venue->getCity(),
                'latitude' => $venue->getLatitude(),
                'longitude' => $venue->getLongitude(),
                'capacity' => $venue->getCapacity(),
                'sport' => $venue->getSport() ? [
                    'id' => $venue->getSport()->getId(),
                    'name' => $venue->getSport()->getName(),
                ] : null,
            ];
        }, $venues);
        
        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'venues_show', methods: ['GET'])]
    public function show(int $id, VenueRepository $venueRepository): JsonResponse
    {
        $venue = $venueRepository->find($id);
        
        if (!$venue) {
            return $this->json([
                'success' => false,
                'message' => 'Lieu non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $venue->getId(),
                'name' => $venue->getName(),
                'type' => $venue->getType(),
                'address' => $venue->getAddress(),
                'city' => $venue->getCity(),
                'postalCode' => $venue->getPostalCode(),
                'latitude' => $venue->getLatitude(),
                'longitude' => $venue->getLongitude(),
                'capacity' => $venue->getCapacity(),
                'facilities' => $venue->getFacilities(),
                'contactEmail' => $venue->getContactEmail(),
                'contactPhone' => $venue->getContactPhone(),
                'website' => $venue->getWebsite(),
                'sport' => $venue->getSport() ? [
                    'id' => $venue->getSport()->getId(),
                    'name' => $venue->getSport()->getName(),
                ] : null,
            ]
        ]);
    }

    #[Route('', name: 'venues_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SportRepository $sportRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $venue = new Venue();
        $venue->setName($data['name']);
        $venue->setType($data['type']);
        $venue->setAddress($data['address']);
        $venue->setCity($data['city']);
        $venue->setPostalCode($data['postalCode'] ?? null);
        $venue->setLatitude($data['latitude']);
        $venue->setLongitude($data['longitude']);
        $venue->setCapacity($data['capacity'] ?? null);
        $venue->setFacilities($data['facilities'] ?? null);
        $venue->setContactEmail($data['contactEmail'] ?? null);
        $venue->setContactPhone($data['contactPhone'] ?? null);
        $venue->setWebsite($data['website'] ?? null);
        $venue->setIsActive(true);
        
        if (isset($data['sportId'])) {
            $sport = $sportRepository->find($data['sportId']);
            if ($sport) {
                $venue->setSport($sport);
            }
        }

        $em->persist($venue);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Lieu créé avec succès',
            'data' => ['id' => $venue->getId()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'venues_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        VenueRepository $venueRepository,
        SportRepository $sportRepository
    ): JsonResponse {
        $venue = $venueRepository->find($id);
        
        if (!$venue) {
            return $this->json([
                'success' => false,
                'message' => 'Lieu non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $venue->setName($data['name']);
        if (isset($data['type'])) $venue->setType($data['type']);
        if (isset($data['address'])) $venue->setAddress($data['address']);
        if (isset($data['city'])) $venue->setCity($data['city']);
        if (isset($data['postalCode'])) $venue->setPostalCode($data['postalCode']);
        if (isset($data['latitude'])) $venue->setLatitude($data['latitude']);
        if (isset($data['longitude'])) $venue->setLongitude($data['longitude']);
        if (isset($data['capacity'])) $venue->setCapacity($data['capacity']);
        if (isset($data['facilities'])) $venue->setFacilities($data['facilities']);
        if (isset($data['contactEmail'])) $venue->setContactEmail($data['contactEmail']);
        if (isset($data['contactPhone'])) $venue->setContactPhone($data['contactPhone']);
        if (isset($data['website'])) $venue->setWebsite($data['website']);
        if (isset($data['isActive'])) $venue->setIsActive($data['isActive']);
        
        if (isset($data['sportId'])) {
            $sport = $sportRepository->find($data['sportId']);
            $venue->setSport($sport);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Lieu mis à jour avec succès'
        ]);
    }

    #[Route('/{id}', name: 'venues_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        VenueRepository $venueRepository
    ): JsonResponse {
        $venue = $venueRepository->find($id);
        
        if (!$venue) {
            return $this->json([
                'success' => false,
                'message' => 'Lieu non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $em->remove($venue);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Lieu supprimé avec succès'
        ]);
    }
}
