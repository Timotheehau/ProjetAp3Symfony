<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    public function testAdminStatsAreProtected(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => 'pointmatch.m2l.lan',
        ]);

        $client->request('GET', '/api/admin/stats');
        $statusCode = $client->getResponse()->getStatusCode();

        $this->assertContains($statusCode, [401, 403]);
    }

    public function testAdminStatsAreAccessibleByAdmin(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => 'pointmatch.m2l.lan',
        ]);

        // 1. On prépare notre utilisateur Admin
        $adminUser = new User();
        $adminUser->setEmail('admin@sports-platform.com');
        $adminUser->setRoles(['ROLE_ADMIN']);

        // 2. On configure une réserve généreuse de résultats pour TOUTES les requêtes SQL de stats
        // (createStub : on ne vérifie pas les appels, juste leur valeur de retour -> pas de notice PHPUnit)
        $dbResultStub = $this->createStub(Result::class);
        $dbResultStub->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['sclr_0' => 150]], // 1. Total utilisateurs
                [['sclr_0' => 42]],  // 2. Total réservations
                [['sclr_0' => 12]],  // 3. Nouveaux utilisateurs (Ligne 116)
                [['sclr_0' => 5]],   // 4. Au cas où il y a une autre stat d'un autre repo
                [['sclr_0' => 0]],   // 5. Sécurité
                [['sclr_0' => 0]]    // 6. Sécurité
            );

        $dbResultStub->method('fetchOne')
            ->willReturnOnConsecutiveCalls(150, 42, 12, 5, 0, 0);

        // 3. On stub la connexion DBAL
        $connectionStub = $this->createStub(Connection::class);
        $connectionStub->method('executeQuery')->willReturn($dbResultStub);

        // 4. Injection dans l'EntityManager d'origine
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $reflection = new \ReflectionClass($entityManager);
        $property = $reflection->getProperty('conn');
        $property->setAccessible(true);
        $property->setValue($entityManager, $connectionStub);

        // 5. Exécution
        $client->loginUser($adminUser);
        $client->request('GET', '/api/admin/stats');

        // 6. Assertions
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }
}
