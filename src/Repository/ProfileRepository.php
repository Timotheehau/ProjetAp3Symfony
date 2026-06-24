<?php

namespace App\Repository;

use App\Entity\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    /**
     * Nombre de réservations par profil et par ville (inclut les profils sans réservation)
     */
    public function countBookingsByProfileAndCity(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id AS id_profile', 'p.city AS ville', 'COUNT(b.id) AS nb_reservations')
            ->leftJoin('p.bookings', 'b')
            ->groupBy('p.id', 'p.city')
            ->orderBy('nb_reservations', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Profile[] Profils n'ayant jamais reçu de réservation
     */
    public function findNeverBooked(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.bookings', 'b')
            ->where('b.id IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Profile[] Le ou les profils ayant la meilleure note pour un sport donné
     */
    public function findTopRatedBySport(int $sportId): array
    {
        $maxRating = $this->bySportBaseQuery($sportId)
            ->select('MAX(p.averageRating)')
            ->andWhere('p.averageRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        if ($maxRating === null) {
            return [];
        }

        return $this->bySportBaseQuery($sportId)
            ->andWhere('p.averageRating = :maxRating')
            ->setParameter('maxRating', $maxRating)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Profile[] Le ou les profils ayant le tarif horaire le plus bas pour un sport donné
     */
    public function findCheapestBySport(int $sportId): array
    {
        $minRate = $this->bySportBaseQuery($sportId)
            ->select('MIN(p.hourlyRate)')
            ->andWhere('p.hourlyRate IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        if ($minRate === null) {
            return [];
        }

        return $this->bySportBaseQuery($sportId)
            ->andWhere('p.hourlyRate = :minRate')
            ->setParameter('minRate', $minRate)
            ->getQuery()
            ->getResult();
    }

    private function bySportBaseQuery(int $sportId): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.sports', 's')
            ->innerJoin('p.user', 'u')
            ->where('s.id = :sportId')
            ->andWhere('u.isActive = :active')
            ->andWhere('p.isVerified = :verified')
            ->setParameter('sportId', $sportId)
            ->setParameter('active', true)
            ->setParameter('verified', true);
    }
}
