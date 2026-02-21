<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use OpenApi\Attributes as OA;

#[Route('/api/users')]
#[OA\Tag(name: 'Users')]
class UsersController extends AbstractController
{
    #[Route('', name: 'users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'Liste tous les utilisateurs',
        security: [['Bearer' => []]],
        tags: ['Users']
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des utilisateurs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                            new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                            new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                            new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                            new OA\Property(property: 'phone', type: 'string', example: '0612345678'),
                            new OA\Property(property: 'userType', type: 'string', example: 'particular')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    public function list(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(function($user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'city' => $user->getCity(),
                'phone' => $user->getPhone(),
                'userType' => $user->getUserType(),
            ];
        }, $users);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    #[Route('/{id}', name: 'users_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Affiche un utilisateur spécifique',
        security: [['Bearer' => []]],
        tags: ['Users']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'Détails de l\'utilisateur'
    )]
    #[OA\Response(
        response: 404,
        description: 'Utilisateur non trouvé'
    )]
    public function show(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'city' => $user->getCity(),
                'phone' => $user->getPhone(),
                'userType' => $user->getUserType(),
            ]
        ]);
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Créer un nouvel utilisateur',
        security: [['Bearer' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password', 'firstName', 'lastName'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'firstName', type: 'string'),
                new OA\Property(property: 'lastName', type: 'string'),
                new OA\Property(property: 'city', type: 'string', format: 'string'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'userType', type: 'string', enum: ['particular', 'professional'])
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur créé avec succès'
    )]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setCity($data['city']);
        $user->setPhone($data['phone'] ?? null);
        $user->setUserType($data['userType'] ?? 'particular');

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ]
        ], Response::HTTP_CREATED);
    }
}
