<?php

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\Profile;
use App\Entity\SessionHistory;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BookingsControllerTest extends WebTestCase
{
    use FixtureTrait;

    private function createBooking(User $client, Profile $profile, \DateTime $start, \DateTime $end, array $overrides = []): Booking
    {
        $booking = new Booking();
        $booking->setClient($client);
        $booking->setProfile($profile);
        $booking->setStartTime($start);
        $booking->setEndTime($end);
        $booking->setStatus($overrides['status'] ?? 'pending');
        $booking->setTotalPrice((string) ($overrides['totalPrice'] ?? '50.00'));

        $this->em()->persist($booking);
        $this->em()->flush();

        return $booking;
    }

    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/bookings');

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testCreateFailsWhenAvailabilityIsMissing(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $particular = $this->createParticularUser();

        $client->loginUser($particular);
        $client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'availabilityId' => 999999,
                'startTime' => '2026-07-01 09:00:00',
                'endTime' => '2026-07-01 10:00:00',
            ])
        );

        $this->assertResponseStatusCodeSame(404);

        $this->cleanup([$particular]);
    }

    public function testCreateSucceedsAndLocksNonRecurringAvailability(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $sport = $this->createSport();
        $availability = $this->createAvailability($profile, $sport, ['isRecurring' => false]);
        $particular = $this->createParticularUser();

        $client->loginUser($particular);
        $client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'availabilityId' => $availability->getId(),
                'startTime' => '2026-07-01 09:00:00',
                'endTime' => '2026-07-01 10:00:00',
                'totalPrice' => 40,
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);

        $this->em()->refresh($availability);
        $this->assertFalse($availability->isAvailable());

        $booking = $this->em()->getRepository(Booking::class)->findOneBy(['profile' => $profile, 'client' => $particular]);
        $this->assertNotNull($booking);
        $this->assertSame('pending', $booking->getStatus());

        $this->cleanup([$booking, $availability, $profile, $coach, $particular, $sport]);
    }

    public function testCreateDetectsOverlappingBookingConflict(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $sport = $this->createSport();
        $availability = $this->createAvailability($profile, $sport, ['isRecurring' => true]);
        $clientA = $this->createParticularUser();

        $payload = [
            'availabilityId' => $availability->getId(),
            'startTime' => '2026-07-02 09:00:00',
            'endTime' => '2026-07-02 10:00:00',
            'totalPrice' => 40,
        ];

        // Le contrôle de conflit ne dépend que du profil + créneau horaire, pas du client :
        // un seul utilisateur suffit. On utilise un 2e client pour la 2e requête car la
        // simulation loginUser() d'un firewall stateless ne couvre fiablement que la requête
        // suivante sur un même client, pas une 2e requête à la suite.
        $client->loginUser($clientA);
        $client->request('POST', '/api/bookings', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $this->assertResponseIsSuccessful();

        $secondClient = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $secondClient->loginUser($clientA);
        $secondClient->request(
            'POST',
            '/api/bookings',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(array_merge($payload, ['startTime' => '2026-07-02 09:30:00', 'endTime' => '2026-07-02 10:30:00']))
        );

        $this->assertResponseStatusCodeSame(409);

        $bookings = $this->em()->getRepository(Booking::class)->findBy(['profile' => $profile]);
        $this->cleanup(array_merge($bookings, [$availability, $profile, $coach, $clientA, $sport]));
    }

    public function testUpdateStatusReturns404ForUnknownBooking(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $user = $this->createParticularUser();

        $client->loginUser($user);
        $client->request(
            'PATCH',
            '/api/bookings/999999/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'confirmed'])
        );

        $this->assertResponseStatusCodeSame(404);

        $this->cleanup([$user]);
    }

    public function testUpdateStatusRejectsInvalidStatusValue(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $particular = $this->createParticularUser();
        $booking = $this->createBooking($particular, $profile, new \DateTime('+1 day'), new \DateTime('+1 day +1 hour'));

        $client->loginUser($particular);
        $client->request(
            'PATCH',
            '/api/bookings/' . $booking->getId() . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'not-a-real-status'])
        );

        $this->assertResponseStatusCodeSame(400);

        $this->cleanup([$booking, $profile, $coach, $particular]);
    }

    public function testUpdateStatusToCancelledRecordsReasonAndActor(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $particular = $this->createParticularUser();
        $booking = $this->createBooking($particular, $profile, new \DateTime('+1 day'), new \DateTime('+1 day +1 hour'));
        $bookingId = $booking->getId();

        $client->loginUser($particular);
        $client->request(
            'PATCH',
            '/api/bookings/' . $bookingId . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'cancelled', 'cancellationReason' => 'Empêchement'])
        );

        $this->assertResponseIsSuccessful();

        $this->em()->refresh($booking);
        $this->assertSame('cancelled', $booking->getStatus());
        $this->assertSame('client', $booking->getCancelledBy());
        $this->assertSame('Empêchement', $booking->getCancellationReason());
        $this->assertNotNull($booking->getCancelledAt());

        $this->cleanup([$booking, $profile, $coach, $particular]);
    }

    public function testUpdateStatusToCompletedFailsForFutureBooking(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $particular = $this->createParticularUser();
        $booking = $this->createBooking($particular, $profile, new \DateTime('+1 day'), new \DateTime('+1 day +1 hour'));

        $client->loginUser($coach);
        $client->request(
            'PATCH',
            '/api/bookings/' . $booking->getId() . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'completed'])
        );

        $this->assertResponseStatusCodeSame(400);

        $this->cleanup([$booking, $profile, $coach, $particular]);
    }

    public function testUpdateStatusToCompletedGeneratesSessionHistory(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $particular = $this->createParticularUser();
        $booking = $this->createBooking(
            $particular,
            $profile,
            new \DateTime('-2 hours'),
            new \DateTime('-1 hour')
        );
        $bookingId = $booking->getId();

        $client->loginUser($coach);
        $client->request(
            'PATCH',
            '/api/bookings/' . $bookingId . '/status',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['status' => 'completed'])
        );

        $this->assertResponseIsSuccessful();

        $this->em()->refresh($booking);
        $this->assertSame('completed', $booking->getStatus());
        $this->assertNotNull($booking->getCompletedAt());

        $history = $this->em()->getRepository(SessionHistory::class)->findOneBy(['booking' => $booking]);
        $this->assertNotNull($history);
        $this->assertSame(60, $history->getDuration());
        $this->assertSame($particular->getId(), $history->getClient()->getId());

        $this->cleanup([$history, $booking, $profile, $coach, $particular]);
    }
}
