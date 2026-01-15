<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Profile;
use App\Entity\Sport;
use App\Entity\Venue;
use App\Entity\Booking;
use App\Entity\Review;
use App\Entity\Availability;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ============================================
        // 1. CRÉER DES SPORTS
        // ============================================
        $sports = [];
        $sportsList = [
            ['name' => 'Football', 'description' => 'Sport collectif le plus populaire au monde', 'icon' => '⚽'],
            ['name' => 'Basketball', 'description' => 'Sport collectif dynamique', 'icon' => '🏀'],
            ['name' => 'Tennis', 'description' => 'Sport de raquette individuel ou en double', 'icon' => '🎾'],
            ['name' => 'Natation', 'description' => 'Sport aquatique complet', 'icon' => '🏊'],
            ['name' => 'Rugby', 'description' => 'Sport collectif de contact', 'icon' => '🏉'],
            ['name' => 'Volleyball', 'description' => 'Sport collectif de filet', 'icon' => '🏐'],
            ['name' => 'Handball', 'description' => 'Sport collectif en salle', 'icon' => '🤾'],
            ['name' => 'Boxe', 'description' => 'Sport de combat', 'icon' => '🥊'],
        ];

        foreach ($sportsList as $sportData) {
            $sport = new Sport();
            $sport->setName($sportData['name']);
            $sport->setDescription($sportData['description']);
            $sport->setIcon($sportData['icon']);
            $sport->setIsActive(true);
            $sport->setCreatedAt(new \DateTimeImmutable());

            $manager->persist($sport);
            $sports[] = $sport;
        }

        // ============================================
        // 2. CRÉER UN ADMIN
        // ============================================
        $admin = new User();
        $admin->setEmail('admin@sports-platform.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('System');
        $admin->setPhone('0600000000');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setUserType('professional');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setIsActive(true);
        $manager->persist($admin);

        // ============================================
        // 3. CRÉER DES UTILISATEURS PARTICULIERS
        // ============================================
        $particulars = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user{$i}@example.com");
            $user->setFirstName("Jean");
            $user->setLastName("Dupont{$i}");
            $user->setPhone("060{$i}000000");
            $user->setUserType('particular');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setIsActive(true);

            $manager->persist($user);
            $particulars[] = $user;
        }

        // ============================================
        // 4. CRÉER DES PROFESSIONNELS AVEC PROFILS
        // ============================================
        $profiles = [];
        $cities = ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nantes', 'Bordeaux', 'Lille'];
        $specialties = ['coach', 'referee', 'health_specialist'];
        $levels = ['pro', 'semi-pro', 'amateur'];

        for ($i = 1; $i <= 15; $i++) {
            // Créer l'utilisateur professionnel
            $pro = new User();
            $pro->setEmail("pro{$i}@example.com");
            $pro->setFirstName("Coach");
            $pro->setLastName("Pro{$i}");
            $pro->setPhone("070{$i}000000");
            $pro->setUserType('professional');
            $pro->setPassword($this->passwordHasher->hashPassword($pro, 'password123'));
            $pro->setIsActive(true);
            $manager->persist($pro);

            // Créer le profil professionnel
            $profile = new Profile();
            $profile->setUser($pro);
            $profile->setSpecialty($specialties[array_rand($specialties)]);
            $profile->setLevel($levels[array_rand($levels)]);
            $profile->setBio("Professionnel passionné avec {$i} ans d'expérience dans le domaine sportif. Diplômé d'État et certifié.");
            $profile->setYearsOfExperience($i);
            $profile->setHourlyRate(30 + ($i * 5));
            $profile->setCity($cities[array_rand($cities)]);
            $profile->setAddress("{$i} Avenue du Sport");
            $profile->setLatitude((string)(48.8566 + (rand(-100, 100) / 100)));
            $profile->setLongitude((string)(2.3522 + (rand(-100, 100) / 100)));
            $profile->setIsVerified(true);
            $profile->setIsActive(true);
            $profile->setVerifiedAt(new \DateTimeImmutable());

            // Ajouter 1 à 3 sports aléatoires
            $numSports = rand(1, 3);
            $selectedSports = array_rand($sports, $numSports);
            if (!is_array($selectedSports)) {
                $selectedSports = [$selectedSports];
            }
            foreach ($selectedSports as $sportIndex) {
                $profile->addSport($sports[$sportIndex]);
            }

            $manager->persist($profile);
            $profiles[] = $profile;

            // ============================================
            // CRÉER DES DISPONIBILITÉS POUR CE PROFIL
            // ============================================
            // Disponibilité lundi à vendredi, 9h-17h
            for ($day = 1; $day <= 5; $day++) {
                $availability = new Availability();
                $availability->setProfile($profile);
                $availability->setDayOfWeek($day);
                $availability->setStartTime(new \DateTime('09:00'));
                $availability->setEndTime(new \DateTime('17:00'));
                $availability->setIsRecurring(true);
                $availability->setIsAvailable(true);
                $manager->persist($availability);
            }
        }

        // ============================================
        // 5. CRÉER DES LIEUX (VENUES)
        // ============================================
        $venues = [];
        $venueTypes = ['stadium', 'gym', 'sports_hall', 'outdoor', 'pool', 'court'];

        for ($i = 1; $i <= 10; $i++) {
            $venue = new Venue();
            $venue->setName("Complexe Sportif #{$i}");
            $venue->setType($venueTypes[array_rand($venueTypes)]);
            $venue->setAddress("{$i} Boulevard du Stade");
            $venue->setCity($cities[array_rand($cities)]);
            $venue->setPostalCode("7500{$i}");
            $venue->setLatitude((string)(48.8566 + (rand(-100, 100) / 100)));
            $venue->setLongitude((string)(2.3522 + (rand(-100, 100) / 100)));
            $venue->setCapacity(rand(50, 500));
            $venue->setContactEmail("contact{$i}@venue.com");
            $venue->setContactPhone("010{$i}000000");
            $venue->setIsActive(true);
            $venue->setSport($sports[array_rand($sports)]);

            $manager->persist($venue);
            $venues[] = $venue;
        }

        // ============================================
        // 6. CRÉER DES RÉSERVATIONS
        // ============================================
        $bookings = [];
        for ($i = 0; $i < 20; $i++) {
            $booking = new Booking();
            $booking->setClient($particulars[array_rand($particulars)]);
            $booking->setProfile($profiles[array_rand($profiles)]);

            if (rand(0, 1)) {
                $booking->setVenue($venues[array_rand($venues)]);
            }

            // Date dans le futur ou le passé
            $daysOffset = rand(-30, 30);
            $startTime = (new \DateTime())->modify("{$daysOffset} days")->setTime(rand(9, 16), 0);
            $endTime = (clone $startTime)->modify('+2 hours');

            $booking->setStartTime($startTime);
            $booking->setEndTime($endTime);

            // Statut aléatoire
            $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
            $status = $statuses[array_rand($statuses)];
            $booking->setStatus($status);

            $booking->setTotalPrice((string)rand(50, 200));
            $booking->setNotes("Session de " . ($i + 1));

            if ($status === 'cancelled') {
                $booking->setCancelledAt(new \DateTimeImmutable());
                $booking->setCancellationReason('Indisponibilité du client');
            }

            if ($status === 'completed') {
                $booking->setCompletedAt(new \DateTimeImmutable());
            }

            $manager->persist($booking);
            $bookings[] = $booking;
        }

        // ============================================
        // 7. CRÉER DES AVIS (REVIEWS)
        // ============================================
        $completedBookings = array_filter($bookings, fn($b) => $b->getStatus() === 'completed');

        foreach ($completedBookings as $booking) {
            // Pas tous les bookings ont un avis
            if (rand(0, 2) === 0) continue;

            $review = new Review();
            $review->setProfile($booking->getProfile());
            $review->setAuthor($booking->getClient());
            $review->setBooking($booking);
            $review->setRating(rand(3, 5));

            $comments = [
                'Excellente session, très professionnel !',
                'Bon coach, à l\'écoute et pédagogue.',
                'Session intéressante, je recommande.',
                'Très satisfait de la prestation.',
                'Professionnel compétent et sympathique.',
            ];
            $review->setComment($comments[array_rand($comments)]);
            $review->setIsVerified(true);
            $review->setLikes(rand(0, 15));

            $manager->persist($review);
        }

        // ============================================
        // FLUSH FINAL
        // ============================================
        $manager->flush();

        echo "\n✅ Fixtures chargées avec succès !\n";
        echo "📊 Résumé :\n";
        echo "   - " . count($sports) . " sports\n";
        echo "   - " . count($particulars) . " utilisateurs particuliers\n";
        echo "   - " . count($profiles) . " professionnels avec profils\n";
        echo "   - " . count($venues) . " lieux\n";
        echo "   - " . count($bookings) . " réservations\n";
        echo "   - Avis créés pour les sessions complétées\n";
        echo "\n🔐 Comptes de test :\n";
        echo "   Admin: admin@sports-platform.com / admin123\n";
        echo "   User: user1@example.com / password123\n";
        echo "   Pro: pro1@example.com / password123\n\n";
    }
}
