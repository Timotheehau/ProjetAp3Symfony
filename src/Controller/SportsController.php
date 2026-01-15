<?php

namespace App\Controller;

use App\Entity\Sport;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sports')]
class SportsController extends AbstractController
{
    #[Route('', name: 'sports_list', methods: ['GET'])]
    public function list(SportRepository $sportRepository): JsonResponse
    {
        $sports = $sportRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        $data = array_map(function($sport) {
            return [
                'id' => $sport->getId(),
                'name' => $sport->getName(),
                'description' => $sport->getDescription(),
                'icon' => $sport->getIcon(),
            ];
        }, $sports);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'sports_show', methods: ['GET'])]
    public function show(int $id, SportRepository $sportRepository): JsonResponse
    {
        $sport = $sportRepository->find($id);

        if (!$sport) {
            return $this->json([
                'success' => false,
                'message' => 'Sport non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $sport->getId(),
                'name' => $sport->getName(),
                'description' => $sport->getDescription(),
                'icon' => $sport->getIcon(),
                'profilesCount' => $sport->getProfiles()->count(),
                'venuesCount' => $sport->getVenues()->count(),
            ]
        ]);
    }

    #[Route('', name: 'sports_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $sport = new Sport();
        $sport->setName($data['name']);
        $sport->setDescription($data['description'] ?? null);
        $sport->setIcon($data['icon'] ?? null);
        $sport->setIsActive(true);
        $sport->setCreatedAt(new \DateTimeImmutable());

        $em->persist($sport);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Sport créé avec succès',
            'data' => ['id' => $sport->getId()]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'sports_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        SportRepository $sportRepository
    ): JsonResponse {
        $sport = $sportRepository->find($id);

        if (!$sport) {
            return $this->json([
                'success' => false,
                'message' => 'Sport non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $sport->setName($data['name']);
        if (isset($data['description'])) $sport->setDescription($data['description']);
        if (isset($data['icon'])) $sport->setIcon($data['icon']);
        if (isset($data['isActive'])) $sport->setIsActive($data['isActive']);

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Sport mis à jour avec succès'
        ]);
    }

    #[Route('/{id}', name: 'sports_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        SportRepository $sportRepository
    ): JsonResponse {
        $sport = $sportRepository->find($id);

        if (!$sport) {return $this->json([
'success' => false,
'message' => 'Sport non trouvé'
], Response::HTTP_NOT_FOUND);
}
    $em->remove($sport);
    $em->flush();

    return $this->json([
        'success' => true,
        'message' => 'Sport supprimé avec succès'
    ]);
}
}
