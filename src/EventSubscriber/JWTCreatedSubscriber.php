<?php

namespace App\EventSubscriber;

use App\Entity\RefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JWTCreatedSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        // Générer un refresh token
        $refreshToken = new RefreshToken();
        $refreshToken->setToken(bin2hex(random_bytes(64)));
        $refreshToken->setUser($user);
        $refreshToken->setExpiresAt((new \DateTime())->modify('+30 days'));

        $this->em->persist($refreshToken);
        $this->em->flush();

        // Ajouter le refresh_token à la réponse
        $data['refresh_token'] = $refreshToken->getToken();

        $event->setData($data);
    }
}
