<?php

namespace App\Tests\Controller;

use App\Entity\Availability;
use App\Entity\Profile;
use App\Entity\Sport;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Helpers communs pour créer/nettoyer des entités réelles dans la base de test
 * (pointmatch_test) depuis les tests fonctionnels de contrôleurs.
 */
trait FixtureTrait
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createSport(?string $name = null): Sport
    {
        $sport = new Sport();
        $sport->setName($name ?? 'TestSport-' . uniqid());
        $sport->setIsActive(true);

        $this->em()->persist($sport);
        $this->em()->flush();

        return $sport;
    }

    private function createParticularUser(array $overrides = []): User
    {
        $user = new User();
        $user->setEmail($overrides['email'] ?? ('client-' . uniqid() . '@test.com'));
        $user->setFirstName($overrides['firstName'] ?? 'Jean');
        $user->setLastName($overrides['lastName'] ?? 'Test');
        $user->setUserType('particular');
        $user->setCity($overrides['city'] ?? null);
        $this->setUserPassword($user, $overrides);

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @return array{0: User, 1: Profile}
     */
    private function createProfessionalUser(array $overrides = []): array
    {
        $user = new User();
        $user->setEmail($overrides['email'] ?? ('coach-' . uniqid() . '@test.com'));
        $user->setFirstName($overrides['firstName'] ?? 'Coach');
        $user->setLastName($overrides['lastName'] ?? 'Test');
        $user->setUserType('professional');
        $this->setUserPassword($user, $overrides);

        $profile = new Profile();
        $profile->setSpecialty($overrides['specialty'] ?? 'coach');
        $profile->setLevel($overrides['level'] ?? 'pro');
        $profile->setCity($overrides['city'] ?? 'TestVille-' . uniqid());
        $profile->setIsVerified($overrides['isVerified'] ?? true);
        $profile->setIsActive(true);

        $user->setProfile($profile);

        $this->em()->persist($user);
        $this->em()->persist($profile);
        $this->em()->flush();

        return [$user, $profile];
    }

    private function createAvailability(Profile $profile, Sport $sport, array $overrides = []): Availability
    {
        $availability = new Availability();
        $availability->setProfile($profile);
        $availability->setSport($sport);
        $availability->setStartTime(new \DateTime($overrides['startTime'] ?? '09:00'));
        $availability->setEndTime(new \DateTime($overrides['endTime'] ?? '10:00'));
        $availability->setIsRecurring($overrides['isRecurring'] ?? false);
        $availability->setIsAvailable($overrides['isAvailable'] ?? true);

        $this->em()->persist($availability);
        $this->em()->flush();

        return $availability;
    }

    private function setUserPassword(User $user, array $overrides): void
    {
        if (isset($overrides['plainPassword'])) {
            $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
            $user->setPassword($hasher->hashPassword($user, $overrides['plainPassword']));
            return;
        }

        // Mot de passe non utilisé par le test (pas de login réel) : valeur bouchon.
        $user->setPassword('unused-' . uniqid());
    }

    /**
     * Supprime les entités créées par un test pour ne pas polluer la base de test.
     */
    private function cleanup(array $entities): void
    {
        $em = $this->em();

        foreach (array_reverse($entities) as $entity) {
            if ($entity !== null && $em->contains($entity)) {
                $em->remove($entity);
            }
        }

        $em->flush();
    }
}
