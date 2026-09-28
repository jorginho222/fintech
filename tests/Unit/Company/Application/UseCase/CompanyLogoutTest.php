<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyLogoutDto;
use App\Company\Application\UseCase\CompanyLogout;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\CurrentAuthTokenProviderInterface;
use App\Company\Domain\Service\RefreshTokenClaims;
use App\Company\Domain\Service\RefreshTokenReaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CompanyLogoutTest extends TestCase
{
    public function testItRevokesTheCurrentAccessTokenByItsJti(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $currentAuthTokenProvider = $this->createMock(CurrentAuthTokenProviderInterface::class);
        $currentAuthTokenProvider->method('getJti')->willReturn('an-access-jti');
        $currentAuthTokenProvider->method('getExpiresAt')->willReturn($expiresAt);

        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->expects(self::never())->method('read');

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (RevokedToken $t): bool => $t->getJti() === 'an-access-jti' && $t->getExpiresAt() === $expiresAt));

        (new CompanyLogout($currentAuthTokenProvider, $revokedTokenRepository, $refreshTokenReader))
            ->execute($this->logoutDto());
    }

    public function testItAlsoRevokesTheRefreshTokenWhenGiven(): void
    {
        $accessExpiresAt  = new \DateTimeImmutable('+1 hour');
        $refreshExpiresAt = new \DateTimeImmutable('+1 week');

        $currentAuthTokenProvider = $this->createMock(CurrentAuthTokenProviderInterface::class);
        $currentAuthTokenProvider->method('getJti')->willReturn('an-access-jti');
        $currentAuthTokenProvider->method('getExpiresAt')->willReturn($accessExpiresAt);

        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->expects(self::once())
            ->method('read')
            ->with('a-refresh-token')
            ->willReturn(new RefreshTokenClaims('company-id', 'a-refresh-jti', $refreshExpiresAt));

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revoked = [];
        $revokedTokenRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (RevokedToken $t) use (&$revoked): void {
                $revoked[] = $t->getJti();
            });

        (new CompanyLogout($currentAuthTokenProvider, $revokedTokenRepository, $refreshTokenReader))
            ->execute($this->logoutDto('a-refresh-token'));

        self::assertSame(['an-access-jti', 'a-refresh-jti'], $revoked);
    }

    public function testAnInvalidRefreshTokenIsIgnoredWithoutFailingTheLogout(): void
    {
        $currentAuthTokenProvider = $this->createMock(CurrentAuthTokenProviderInterface::class);
        $currentAuthTokenProvider->method('getJti')->willReturn('an-access-jti');
        $currentAuthTokenProvider->method('getExpiresAt')->willReturn(new \DateTimeImmutable('+1 hour'));

        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')->willThrowException(new InvalidRefreshTokenException());

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::once())->method('save');

        (new CompanyLogout($currentAuthTokenProvider, $revokedTokenRepository, $refreshTokenReader))
            ->execute($this->logoutDto('garbage'));
    }

    private function logoutDto(?string $refreshToken = null): CompanyLogoutDto
    {
        $body = $refreshToken === null ? '' : json_encode(['refreshToken' => $refreshToken], JSON_THROW_ON_ERROR);

        return new CompanyLogoutDto(new Request([], [], [], [], [], [], $body));
    }
}
