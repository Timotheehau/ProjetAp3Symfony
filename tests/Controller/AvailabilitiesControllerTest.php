<?php

namespace App\Tests\Controller;

use App\Entity\Availability;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AvailabilitiesControllerTest extends WebTestCase
{
    use FixtureTrait;

    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/availabilities');

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testCreateFailsWithoutProfessionalProfile(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $particular = $this->createParticularUser();
        $sport = $this->createSport();

        $client->loginUser($particular);
        $client->request(
            'POST',
            '/api/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sportId' => $sport->getId(), 'startTime' => '09:00', 'endTime' => '10:00'])
        );

        $this->assertResponseStatusCodeSame(403);

        $this->cleanup([$particular, $sport]);
    }

    public function testCreateFailsWhenSportIdMissing(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();

        $client->loginUser($coach);
        $client->request(
            'POST',
            '/api/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['startTime' => '09:00', 'endTime' => '10:00'])
        );

        $this->assertResponseStatusCodeSame(400);

        $this->cleanup([$profile, $coach]);
    }

    public function testCreateFailsWhenEndTimeBeforeStartTime(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $sport = $this->createSport();

        $client->loginUser($coach);
        $client->request(
            'POST',
            '/api/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sportId' => $sport->getId(), 'startTime' => '10:00', 'endTime' => '09:00'])
        );

        $this->assertResponseStatusCodeSame(400);

        $this->cleanup([$profile, $coach, $sport]);
    }

    public function testCreateSucceedsForProfessionalWithProfile(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$coach, $profile] = $this->createProfessionalUser();
        $sport = $this->createSport();

        $client->loginUser($coach);
        $client->request(
            'POST',
            '/api/availabilities',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'sportId' => $sport->getId(),
                'startTime' => '09:00',
                'endTime' => '10:00',
                'isRecurring' => true,
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);

        $created = $this->em()->getRepository(Availability::class)->find($data['id']);
        $this->assertNotNull($created);
        $this->assertSame($profile->getId(), $created->getProfile()->getId());

        $this->cleanup([$created, $profile, $coach, $sport]);
    }

    public function testDeleteIsForbiddenForNonOwner(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$owner, $ownerProfile] = $this->createProfessionalUser();
        [$intruder, $intruderProfile] = $this->createProfessionalUser();
        $sport = $this->createSport();
        $availability = $this->createAvailability($ownerProfile, $sport);

        $client->loginUser($intruder);
        $client->request('DELETE', '/api/availabilities/' . $availability->getId());

        $this->assertResponseStatusCodeSame(403);

        $this->cleanup([$availability, $ownerProfile, $owner, $intruderProfile, $intruder, $sport]);
    }

    public function testDeleteSucceedsForOwner(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$owner, $ownerProfile] = $this->createProfessionalUser();
        $sport = $this->createSport();
        $availability = $this->createAvailability($ownerProfile, $sport);
        $availabilityId = $availability->getId();

        $client->loginUser($owner);
        $client->request('DELETE', '/api/availabilities/' . $availabilityId);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->em()->getRepository(Availability::class)->find($availabilityId));

        $this->cleanup([$ownerProfile, $owner, $sport]);
    }
}
