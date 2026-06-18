<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SportsControllerTest extends WebTestCase
{
    use FixtureTrait;

    public function testListIsPubliclyAccessible(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/sports');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['data']);
    }

    public function testListIncludesNewlyCreatedSport(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $sport = $this->createSport('Padel-' . uniqid());

        $client->request('GET', '/api/sports');
        $data = json_decode($client->getResponse()->getContent(), true);

        $names = array_column($data['data'], 'name');
        $this->assertContains($sport->getName(), $names);

        $this->cleanup([$sport]);
    }
}
