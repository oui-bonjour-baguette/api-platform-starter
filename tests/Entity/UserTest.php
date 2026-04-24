<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UserTest extends ApiTestCase
{
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testGetMeAsAuthenticatedUser(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'alice@example.com']);

        self::assertEquals('alice@example.com', $user->getUserIdentifier());

        $client->loginUser($user);

        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => 'alice@example.com']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function testAdminCanAccessAnotherUser(): void
    {
        $client = self::createClient();

        $admin = UserFactory::new()->asAdmin()->create();
        $otherUser = UserFactory::createOne(['email' => 'bob@example.com']);

        $client->loginUser($admin);

        $client->request('GET', '/api/users/'.$otherUser->getId());

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => 'bob@example.com']);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testUserCannotAccessAnotherUser(): void
    {
        $client = self::createClient();

        $alice = UserFactory::createOne();
        $bob = UserFactory::createOne();

        $client->loginUser($alice);

        $client->request('GET', '/api/users/'.$bob->getId());

        self::assertResponseStatusCodeSame(403);
    }
}
