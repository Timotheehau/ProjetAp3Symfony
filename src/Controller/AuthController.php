<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\Sport;
use App\Entity\RefreshToken;
use App\Repository\SportRepository;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        summary: 'Créer un nouveau compte utilisateur avec documents',
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['email', 'password', 'firstName', 'lastName', 'userType'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'firstName', type: 'string'),
                    new OA\Property(property: 'lastName', type: 'string'),
                    new OA\Property(property: 'userType', type: 'string', enum: ['particular', 'professional']),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string', nullable: true),
                    new OA\Property(property: 'level', type: 'string', nullable: true),
                    new OA\Property(property: 'years_of_experience', type: 'integer', nullable: true),
                    new OA\Property(property: 'sports', type: 'string', description: 'JSON array stringified [1,2]'),
                    new OA\Property(property: 'diplomas', type: 'string', format: 'binary'),
                    new OA\Property(property: 'certifications', type: 'string', format: 'binary')
                ]
            )
        )
    )]
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Créer un nouveau compte utilisateur avec documents et validation de sécurité',
        tags: ['Authentication']
    )]
    public function register(
        Request                     $request,
        EntityManagerInterface      $em,
        UserPasswordHasherInterface $passwordHasher,
        SportRepository             $sportRepo,
        ValidatorInterface $validator
    ): JsonResponse
    {
        // 1. Récupération des données via Request (FormData)
        $data = $request->request->all();

        // 2. Récupération des fichiers binaires
        $diplomaFile = $request->files->get('diplomas');
        $certifFile = $request->files->get('certifications');

        // Validation de présence des données de base
        if (!isset($data['email'], $data['password'], $data['firstName'], $data['lastName'])) {
            return $this->json([
                'success' => false,
                'message' => 'Données obligatoires manquantes'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérification unicité Email
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Un utilisateur avec cet email existe déjà'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 3. Initialisation de l'utilisateur avec le mot de passe en clair pour validation
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPlainPassword($data['password']); // On passe le mot de passe ici pour la Regex

        $user->setPhone($data['phone'] ?? null);
        $user->setCity($data['city'] ?? null);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $userType = $data['userType'] ?? 'particular';
        $user->setUserType($userType);

        // --- SÉCURITÉ : VALIDATION DES CONTRAINTES (Regex, Longueur, etc.) ---
        // On valide avec le groupe 'registration' défini dans l'entité
        $violations = $validator->validate($user, null, ['registration', 'Default']);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Erreur de validation des données',
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        // --- SI VALIDATION OK : HACHAGE DU MOT DE PASSE ---
        $hashedPassword = $passwordHasher->hashPassword($user, $user->getPlainPassword());
        $user->setPassword($hashedPassword);
        $user->setPlainPassword(null); // On vide le mot de passe en clair par sécurité

        // 4. Gestion des Sports (Particuliers)
        $sportsData = $data['sports'] ?? '[]';
        $sportIds = is_array($sportsData) ? $sportsData : json_decode($sportsData, true);

        if ($userType === 'particular' && is_array($sportIds)) {
            foreach ($sportIds as $sportId) {
                $sport = $sportRepo->find($sportId);
                if ($sport) $user->addSport($sport);
            }
        }

        // 5. Gestion du Profil (Professionnels)
        if ($userType === 'professional') {
            $profile = new Profile();
            $profile->setCity($data['city'] ?? 'Non renseignée');
            $profile->setLevel($data['level'] ?? 'Débutant');
            $profile->setYearsOfExperience((int)($data['years_of_experience'] ?? 0));
            $profile->setSpecialty('Coach Sportif');
            $profile->setTotalReviews(0);
            $profile->setIsVerified(false);
            $profile->setIsActive(true);
            $profile->setCreatedAt(new \DateTimeImmutable());
            $profile->setUpdatedAt(new \DateTimeImmutable());

            // Géocodage
            $cityInput = $data['city'] ?? null;
            if ($cityInput) {
                try {
                    $cleanCity = trim(preg_replace('/\s*\(.*\)\s*/', '', $cityInput));
                    $cleanCity = str_replace(' - ', ' ', $cleanCity);
                    $apiUrl = "https://api-adresse.data.gouv.fr/search/?q=" . urlencode($cleanCity) . "&limit=1";
                    $response = @file_get_contents($apiUrl);
                    if ($response) {
                        $geoData = json_decode($response, true);
                        if (!empty($geoData['features'])) {
                            $coordinates = $geoData['features'][0]['geometry']['coordinates'];
                            $profile->setLongitude((string)$coordinates[0]);
                            $profile->setLatitude((string)$coordinates[1]);
                        }
                    }
                } catch (\Exception $e) {}
            }

            // Upload des documents
            if ($diplomaFile) {
                $newDiplomaName = uniqid('dip_').'.'.$diplomaFile->guessExtension();
                $diplomaFile->move($this->getParameter('uploads_directory'), $newDiplomaName);
                $profile->setDiplomas($newDiplomaName);
            }

            if ($certifFile) {
                $newCertName = uniqid('cert_').'.'.$certifFile->guessExtension();
                $certifFile->move($this->getParameter('uploads_directory'), $newCertName);
                $profile->setCertifications($newCertName);
            }

            if (is_array($sportIds)) {
                foreach ($sportIds as $sportId) {
                    $sport = $sportRepo->find($sportId);
                    if ($sport) $profile->addSport($sport);
                }
            }

            $user->setProfile($profile);
            $em->persist($profile);
        }

        // 6. Sauvegarde finale
        $em->persist($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => ['id' => $user->getId()]
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
        Request                $request,
        RefreshTokenRepository $refreshTokenRepository,
        EntityManagerInterface $em
    ): JsonResponse
    {
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
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // 1. Initialisation des variables de profil et de vérification
        $profileData = null;
        $userSports = [];

        // Par défaut, un particulier ou un admin est considéré comme "vérifié"
        // car ils n'ont pas de documents à faire valider.
        $isVerified = true;

        // 2. Traitement des sports rattachés directement au User (Particuliers)
        foreach ($user->getSports() as $sport) {
            $userSports[] = [
                'id' => $sport->getId(),
                'name' => $sport->getName(),
                'icon' => $sport->getIcon()
            ];
        }

        // 3. Traitement spécifique au profil Professionnel (Coach)
        if ($user->getUserType() === 'professional' && $user->getProfile()) {
            $profile = $user->getProfile();

            // On récupère le statut de vérification réel du coach
            $isVerified = $profile->getIsVerified();

            $profileSports = [];
            foreach ($profile->getSports() as $sport) {
                $profileSports[] = [
                    'id' => $sport->getId(),
                    'name' => $sport->getName(),
                    'icon' => $sport->getIcon()
                ];
            }

            $profileData = [
                'id' => $profile->getId(),
                'city' => $profile->getCity(),
                'bio' => $profile->getBio(),
                'level' => $profile->getLevel(),
                'yearsOfExperience' => $profile->getYearsOfExperience(),
                'hourlyRate' => $profile->getHourlyRate(),
                'isVerified' => $isVerified,
                'diplomas' => $profile->getDiplomas(),
                'certifications' => $profile->getCertifications(),
                'sports' => $profileSports,
            ];
        }

        // 4. Retour de la réponse JSON structurée
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'city' => $user->getCity(),
            'userType' => $user->getUserType(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'isVerified' => $isVerified, // Champ crucial pour le blocage Frontend
            'createdAt' => $user->getCreatedAt() ? $user->getCreatedAt()->format(\DateTimeInterface::ATOM) : null,
            'updatedAt' => $user->getUpdatedAt() ? $user->getUpdatedAt()->format(\DateTimeInterface::ATOM) : null,
            'sports' => $userSports,
            'profile' => $profileData
        ]);
    }

    #[Route('/api/me/delete', name: 'api_me_delete', methods: ['DELETE'])]
    public function deleteAccount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Optionnel : Supprimer manuellement les tokens liés si nécessaire
        // Mais Doctrine s'en occupe si tu as mis "onDelete: CASCADE"

        $em->remove($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Compte supprimé avec succès'
        ]);
    }

    // src/Controller/AuthController.php

    #[Route('/api/me/update', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        SportRepository $sportRepo
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        // 1. Mise à jour des infos de base (Table User)
        $user->setFirstName($data['firstName'] ?? $user->getFirstName());
        $user->setLastName($data['lastName'] ?? $user->getLastName());
        $user->setPhone($data['phone'] ?? $user->getPhone());
        $user->setCity($data['city'] ?? $user->getCity());
        $user->setUpdatedAt(new \DateTimeImmutable());

        // 2. Gestion des Sports (Table de liaison user_sport)
        if (isset($data['sports']) && is_array($data['sports'])) {
            // On vide les sports actuels
            foreach ($user->getSports() as $sport) {
                $user->removeSport($sport);
            }

            // On ajoute les nouveaux sports sélectionnés
            foreach ($data['sports'] as $sportData) {
                $sport = $sportRepo->find($sportData['id']);
                if ($sport) {
                    $user->addSport($sport);
                }
            }
        }

        // 3. Gestion spécifique au profil Pro (si applicable)
        if ($user->getUserType() === 'professional' && $user->getProfile()) {
            $profile = $user->getProfile();
            $profileData = $data['profile'] ?? [];

            $profile->setCity($profileData['city'] ?? $profile->getCity());
            $profile->setBio($profileData['bio'] ?? $profile->getBio());
            $profile->setHourlyRate($profileData['hourlyRate'] ?? $profile->getHourlyRate());

            // Si tu stockes AUSSI les sports dans Profile pour les pros :
            if (isset($profileData['sports'])) {
                foreach ($profile->getSports() as $s) $profile->removeSport($s);
                foreach ($profileData['sports'] as $sData) {
                    $s = $sportRepo->find($sData['id']);
                    if ($s) $profile->addSport($s);
                }
            }
        }

        $em->flush();

        return $this->json(['success' => true, 'message' => 'Profil mis à jour']);
    }
}
