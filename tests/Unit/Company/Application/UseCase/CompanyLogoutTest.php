<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\UseCase\CompanyLogout;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\CurrentAuthTokenProviderInterface;
use PHPUnit\Framework\TestCase;

final class CompanyLogoutTest extends TestCase
{
    public function testItRevokesTheCurrentTokenByItsJti(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $currentAuthTokenProvider = $this->createMock(CurrentAuthTokenProviderInterface::class);
        $currentAuthTokenProvider->method('getJti')->willReturn('a-jti');
        $currentAuthTokenProvider->method('getExpiresAt')->willReturn($expiresAt);

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (RevokedToken $revokedToken) use ($expiresAt): bool {
                return $revokedToken->getJti() === 'a-jti' && $revokedToken->getExpiresAt() === $expiresAt;
            }));

        (new CompanyLogout($currentAuthTokenProvider, $revokedTokenRepository))->execute();
    }
}
