<?php

namespace App\Controller;

use App\Entity\Equipment;
use App\Repository\EquipmentRepository;
use App\Repository\SportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/equipment')]
#[OA\Tag(name: 'Equipment')]
class EquipmentController extends AbstractController
{
    /**
     * Liste les équipements à apporter pour un sport donné
     */
    #[Route('', name: 'equipment_list', methods: ['GET'])]
    public function list(Request $request, EquipmentRepository $equipmentRepository): JsonResponse
    {
        $sportId = $request->query->get('sportId');

        if (!$sportId) {
            return $this->json(['success' => false, 'message' => 'Le paramètre sportId est requis'], 400);
        }

        $equipments = $equipmentRepository->findBy(['sport' => $sportId]);

        $data = array_map(function(Equipment $equipment) {
            return [
                'id' => $equipment->getId(),
                'name' => $equipment->getName(),
                'description' => $equipment->getDescription(),
            ];
        }, $equipments);

        return $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * Ajoute un équipement à la liste d'un sport (coach ou admin)
     */
    #[Route('', name: 'equipment_create', methods: ['POST'])]
    #[IsGranted('ROLE_COACH')]
    public function create(Request $request, SportRepository $sportRepository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $sport = isset($data['sportId']) ? $sportRepository->find($data['sportId']) : null;
        if (!$sport) {
            return $this->json(['success' => false, 'message' => 'Sport non trouvé'], 404);
        }

        if (empty($data['name'])) {
            return $this->json(['success' => false, 'message' => "Le nom de l'équipement est obligatoire"], 400);
        }

        $equipment = new Equipment();
        $equipment->setSport($sport);
        $equipment->setName($data['name']);
        $equipment->setDescription($data['description'] ?? null);

        $em->persist($equipment);
        $em->flush();

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $equipment->getId(),
                'name' => $equipment->getName(),
                'description' => $equipment->getDescription(),
            ]
        ], 201);
    }

    /**
     * Supprime un équipement (coach ou admin)
     */
    #[Route('/{id}', name: 'equipment_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_COACH')]
    public function delete(int $id, EquipmentRepository $equipmentRepository, EntityManagerInterface $em): JsonResponse
    {
        $equipment = $equipmentRepository->find($id);
        if (!$equipment) {
            return $this->json(['success' => false, 'message' => 'Équipement non trouvé'], 404);
        }

        $em->remove($equipment);
        $em->flush();

        return $this->json(['success' => true]);
    }
}
