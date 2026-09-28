<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Application\UseCase\CompanyTokenRefresher;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\RefreshTokenClaims;
use App\Company\Domain\Service\RefreshTokenReaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class CompanyTokenRefresherTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function testItRotatesTheRefreshTokenAndIssuesANewPair(): void
    {
        $company        = $this->company();
        $refreshExpires = new \DateTimeImmutable('+1 week');
        $newAccess      = new AuthToken('new.access.token', new \DateTimeImmutable('+1 hour'));
        $newRefresh     = new AuthToken('new.refresh.token', new \DateTimeImmutable('+1 week'));

        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')
            ->with('old-refresh-token')
            ->willReturn(new RefreshTokenClaims(self::COMPANY_ID, 'old-refresh-jti', $refreshExpires));

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('findById')->with(self::COMPANY_ID)->willReturn($company);

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (RevokedToken $t): bool => $t->getJti() === 'old-refresh-jti' && $t->getExpiresAt() === $refreshExpires));

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::once())->method('generateAccessTokenFor')->with($company)->willReturn($newAccess);
        $tokenGenerator->expects(self::once())->method('generateRefreshTokenFor')->with($company)->willReturn($newRefresh);

        $result = (new CompanyTokenRefresher($refreshTokenReader, $companyRepository, $revokedTokenRepository, $tokenGenerator))
            ->execute($this->refreshTokenDto('old-refresh-token'));

        self::assertSame($company, $result->company);
        self::assertSame($newAccess, $result->accessToken);
        self::assertSame($newRefresh, $result->refreshToken);
    }

    public function testAnInvalidRefreshTokenIsRejectedWithoutIssuingNewTokens(): void
    {
        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')->willThrowException(new InvalidRefreshTokenException());

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->expects(self::never())->method('findById');

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::never())->method('save');

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');

        $this->expectException(InvalidRefreshTokenException::class);

        (new CompanyTokenRefresher($refreshTokenReader, $companyRepository, $revokedTokenRepository, $tokenGenerator))
            ->execute($this->refreshTokenDto('garbage'));
    }

    public function testARefreshTokenForADeletedCompanyIsRejected(): void
    {
        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')
            ->willReturn(new RefreshTokenClaims(self::COMPANY_ID, 'a-jti', new \DateTimeImmutable('+1 week')));

        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('findById')->willReturn(null);

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');

        $this->expectException(InvalidRefreshTokenException::class);

        (new CompanyTokenRefresher($refreshTokenReader, $companyRepository, $revokedTokenRepository, $tokenGenerator))
            ->execute($this->refreshTokenDto('a-refresh-token'));
    }

    private function company(): Company
    {
        return new Company(
            self::COMPANY_ID,
            'Empresa SRL',
            '20123456789',
            'empresa@empresa.com',
            TaxStatus::Monotributo,
            'hashed-password',
        );
    }

    private function refreshTokenDto(string $refreshToken): RefreshTokenDto
    {
        $request = new Request([], [], [], [], [], [], json_encode(['refreshToken' => $refreshToken], JSON_THROW_ON_ERROR));

        return new RefreshTokenDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
