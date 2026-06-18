<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UsersControllerTest extends WebTestCase
{
    use FixtureTrait;

    public function testListRequiresAuthentication(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);

        $client->request('GET', '/api/users');

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testListReturnsUsersWhenAuthenticated(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $user = $this->createParticularUser();

        $client->loginUser($user);
        $client->request('GET', '/api/users');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data['data'], 'email');
        $this->assertContains($user->getEmail(), $emails);

        $this->cleanup([$user]);
    }

    public function testShowReturns404ForUnknownUser(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $user = $this->createParticularUser();

        $client->loginUser($user);
        $client->request('GET', '/api/users/999999');

        $this->assertResponseStatusCodeSame(404);

        $this->cleanup([$user]);
    }

    public function testShowReturnsRequestedUserData(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $requester = $this->createParticularUser();
        $target = $this->createParticularUser(['firstName' => 'Alice', 'lastName' => 'Martin']);

        $client->loginUser($requester);
        $client->request('GET', '/api/users/' . $target->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Alice', $data['data']['firstName']);
        $this->assertSame($target->getEmail(), $data['data']['email']);

        $this->cleanup([$requester, $target]);
    }

    public function testCreatePersistsNewUser(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'pointmatch.m2l.lan']);
        $requester = $this->createParticularUser();
        $newEmail = 'new-' . uniqid() . '@test.com';

        $client->loginUser($requester);
        $client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $newEmail,
                'password' => 'Password123!',
                'firstName' => 'New',
                'lastName' => 'User',
                'city' => 'Paris',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($newEmail, $data['data']['email']);

        $created = $this->em()->getRepository(\App\Entity\User::class)->find($data['data']['id']);
        $this->assertNotNull($created);

        $this->cleanup([$requester, $created]);
    }
}
