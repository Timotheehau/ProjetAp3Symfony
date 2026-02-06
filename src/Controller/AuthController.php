<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;

class AuthController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Créer un nouveau compte utilisateur',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password', 'firstName', 'lastName'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password123!'),
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'phone', type: 'string', example: '0612345678', nullable: true),
                new OA\Property(property: 'userType', type: 'string', enum: ['particular', 'professional'], example: 'particular')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur créé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Utilisateur créé avec succès'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com')
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Email déjà utilisé ou données invalides')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Un utilisateur avec cet email existe déjà'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
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

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Se connecter et obtenir un token JWT + refresh token',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Password123!')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Connexion réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                new OA\Property(property: 'refresh_token', type: 'string', example: 'abc123def456...')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Identifiants invalides')]
    public function login(): JsonResponse
    {
        // Cette méthode est gérée par LexikJWTAuthenticationBundle
        // mais on va intercepter la réponse pour ajouter le refresh_token
        return $this->json(['message' => 'Cette route est gérée par le système JWT']);
    }

    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    #[OA\Post(
        path: '/api/token/refresh',
        summary: 'Rafraîchir le token JWT',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string', example: 'abc123def456...')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Token rafraîchi avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'refresh_token', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Refresh token invalide ou expiré')]
    public function refresh(
        Request $request,
        RefreshTokenRepository $refreshTokenRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $refreshTokenString = $data['refresh_token'] ?? null;

        if (!$refreshTokenString) {
            return $this->json([
                'success' => false,
                'message' => 'Refresh token requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshToken = $refreshTokenRepository->findOneByToken($refreshTokenString);

        if (!$refreshToken || $refreshToken->isExpired()) {
            return $this->json([
                'success' => false,
                'message' => 'Refresh token invalide ou expiré'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->getUser();

        // Générer un nouveau JWT
        $newToken = $this->jwtManager->create($user);

        // Générer un nouveau refresh token
        $newRefreshToken = new RefreshToken();
        $newRefreshToken->setToken(bin2hex(random_bytes(64)));
        $newRefreshToken->setUser($user);
        $newRefreshToken->setExpiresAt((new \DateTime())->modify('+30 days'));

        $em->persist($newRefreshToken);
        
        // Supprimer l'ancien refresh token
        $em->remove($refreshToken);
        
        $em->flush();

        return $this->json([
            'token' => $newToken,
            'refresh_token' => $newRefreshToken->getToken()
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/me',
        summary: 'Obtenir les informations de l\'utilisateur connecté',
        security: [['Bearer' => []]],
        tags: ['Authentication']
    )]
    #[OA\Response(
        response: 200,
        description: 'Informations utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'user',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'phone', type: 'string', example: '0612345678'),
                        new OA\Property(property: 'userType', type: 'string', example: 'particular'),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['ROLE_USER']
                        )
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Non authentifié')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        // Ensure the returned user is an instance of our User entity
        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'phone' => $user->getPhone(),
                'userType' => $user->getUserType(),
                'roles' => $user->getRoles()
            ]
        ]);
    }
}
