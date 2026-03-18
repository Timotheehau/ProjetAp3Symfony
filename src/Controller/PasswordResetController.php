<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Repository\UserRepository;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
class PasswordResetController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        PasswordResetTokenRepository $tokenRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        // LOG 1 : On vérifie ce que le front envoie
        error_log("DEBUG MAILER: Email reçu du front : " . ($email ?? 'NULL'));

        if (!$email) {
            return $this->json(['message' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            // LOG 2 : Si l'utilisateur n'est pas trouvé
            error_log("DEBUG MAILER: Utilisateur NON TROUVE en base pour : " . $email);
            return $this->json(['message' => 'Si cet email existe, un lien a été envoyé.']);
        }

        // LOG 3 : L'utilisateur existe, on continue
        error_log("DEBUG MAILER: Utilisateur TROUVE : " . $user->getEmail() . " (ID: " . $user->getId() . ")");

        // Supprimer les anciens tokens
        $oldTokens = $tokenRepository->findBy(['user' => $user]);
        foreach ($oldTokens as $oldToken) {
            $em->remove($oldToken);
        }

        // Créer un nouveau token
        $token = bin2hex(random_bytes(32));
        $resetToken = new PasswordResetToken();
        $resetToken->setToken($token);
        $resetToken->setUser($user);
        $resetToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $em->persist($resetToken);
        $em->flush();

        // LOG 4 : Token créé en base
        error_log("DEBUG MAILER: Token généré et enregistré en BDD.");

        // Envoyer le mail
        $resetUrl = 'https://pointmatchfront.vercel.app/reset-password?token=' . $token;

        $emailMessage = (new Email())
            ->from(new Address('titi.hauser@gmail.com', 'PointMatch')) // Identité complète
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html("
                <h2>Réinitialisation de mot de passe</h2>
                <p>Bonjour {$user->getFirstName()},</p>
                <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                <a href='{$resetUrl}' style='background:#ef2c44;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;'>
                    Réinitialiser mon mot de passe
                </a>
                <p>Ce lien expire dans 1 heure.</p>
                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
            ");

        try {
            // LOG 5 : Juste avant l'envoi
            error_log("DEBUG MAILER: Tentative d'envoi via MailerInterface...");
// COPIE-COLLE TA LIGNE MAILTRAP ICI EN DUR
// On passe de 2525 à 587
            $dsn = 'smtps://d599cb7128f924:6c6d278348@sandbox.smtp.mailtrap.io:465?auth_mode=login';
            $transport = Transport::fromDsn($dsn);
            $realMailer = new Mailer($transport);

            $realMailer->send($emailMessage);
            error_log("DEBUG MAILER: ENVOI FORCE EN DUR REUSSI");

            // LOG 6 : Si on arrive ici, c'est que send() n'a pas crashé
            error_log("DEBUG MAILER: Commande mailer->send() terminée avec succès.");

        } catch (\Exception $e) {
            // LOG 7 : Si une erreur survient pendant l'envoi (ex: SMTP refusé)
            error_log("DEBUG MAILER ERROR: L'envoi a échoué ! Erreur : " . $e->getMessage());
        }

        return $this->json(['message' => 'Si cet email existe, un lien a été envoyé.']);
    }

    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        PasswordResetTokenRepository $tokenRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $tokenString = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$tokenString || !$newPassword) {
            return $this->json(['message' => 'Token et mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        $resetToken = $tokenRepository->findOneBy(['token' => $tokenString]);

        if (!$resetToken) {
            return $this->json(['message' => 'Token invalide'], Response::HTTP_BAD_REQUEST);
        }

        if ($resetToken->getExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Token expiré'], Response::HTTP_BAD_REQUEST);
        }

        if ($resetToken->getUsedAt()) {
            return $this->json(['message' => 'Token déjà utilisé'], Response::HTTP_BAD_REQUEST);
        }

        $user = $resetToken->getUser();
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $resetToken->setUsedAt(new \DateTimeImmutable());

        $em->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}
