<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfilesControllerTest extends WebTestCase
{
    use FixtureTrait;

    public function testListIsPubliclyAccessible(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/profiles');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function testListFiltersByCity(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $uniqueCity = 'Ville-' . uniqid();
        [$user, $profile] = $this->createProfessionalUser(['city' => $uniqueCity]);

        $client->request('GET', '/api/profiles?city=' . urlencode($uniqueCity));
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(1, $data['data']);
        $this->assertSame($profile->getId(), $data['data'][0]['id']);
        $this->assertSame($uniqueCity, $data['data'][0]['city']);

        $this->cleanup([$profile, $user]);
    }

    public function testListFiltersBySport(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $sport = $this->createSport();
        [$user, $profile] = $this->createProfessionalUser();
        $profile->addSport($sport);
        $this->em()->flush();

        $client->request('GET', '/api/profiles?sportId=' . $sport->getId());
        $data = json_decode($client->getResponse()->getContent(), true);

        $ids = array_column($data['data'], 'id');
        $this->assertContains($profile->getId(), $ids);

        $this->cleanup([$profile, $user, $sport]);
    }

    public function testShowReturns404ForUnknownProfile(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/profiles/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowReturnsProfileDetails(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        [$user, $profile] = $this->createProfessionalUser([
            'specialty' => 'coach',
            'city' => 'Lyon-' . uniqid(),
        ]);

        $client->request('GET', '/api/profiles/' . $profile->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('coach', $data['data']['specialty']);
        $this->assertSame($user->getEmail(), $data['data']['user']['email']);

        $this->cleanup([$profile, $user]);
    }
}
