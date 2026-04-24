<?php

declare(strict_types=1);

namespace App\Tests\State\User;

use ApiPlatform\Metadata\Get;
use App\Entity\User;
use App\State\User\MeProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class MeProviderTest extends TestCase
{
    public function testProvideReturnsCurrentUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $securityMock = $this->createMock(Security::class);
        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $provider = new MeProvider($securityMock);

        $result = $provider->provide(new Get(), []);

        $this->assertSame($user, $result);
    }

    public function testProvideReturnsNullWhenNotAuthenticated(): void
    {
        $securityMock = $this->createMock(Security::class);
        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $provider = new MeProvider($securityMock);
        $result = $provider->provide(new Get(), []);

        $this->assertNull($result);
    }
}
