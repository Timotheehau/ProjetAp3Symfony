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
}
