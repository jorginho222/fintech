<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Application\UseCase\CompanyUserTokenRefresher;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\RefreshTokenClaims;
use App\Company\Domain\Service\RefreshTokenReaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class CompanyUserTokenRefresherTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const string USER_ID    = 'cccccccc-cccc-4ccc-cccc-cccccccccccc';

    public function testItRotatesTheRefreshTokenAndIssuesANewPair(): void
    {
        $admin          = $this->admin();
        $refreshExpires = new \DateTimeImmutable('+1 week');
        $newAccess      = new AuthToken('new.access.token', new \DateTimeImmutable('+1 hour'));
        $newRefresh     = new AuthToken('new.refresh.token', new \DateTimeImmutable('+1 week'));

        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')
            ->with('old-refresh-token')
            ->willReturn(new RefreshTokenClaims(self::USER_ID, 'old-refresh-jti', $refreshExpires));

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->with(self::USER_ID)->willReturn($admin);

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (RevokedToken $t): bool => $t->getJti() === 'old-refresh-jti' && $t->getExpiresAt() === $refreshExpires));

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::once())->method('generateAccessTokenFor')->with($admin)->willReturn($newAccess);
        $tokenGenerator->expects(self::once())->method('generateRefreshTokenFor')->with($admin)->willReturn($newRefresh);

        $result = (new CompanyUserTokenRefresher($refreshTokenReader, $userRepository, $revokedTokenRepository, $tokenGenerator))
            ->execute($this->refreshTokenDto('old-refresh-token'));

        self::assertSame($admin, $result->user);
        self::assertSame($admin->getCompany(), $result->company);
        self::assertSame($newAccess, $result->accessToken);
        self::assertSame($newRefresh, $result->refreshToken);
    }

    public function testAnInvalidRefreshTokenIsRejectedWithoutIssuingNewTokens(): void
    {
        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')->willThrowException(new InvalidRefreshTokenException());

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('findById');

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $revokedTokenRepository->expects(self::never())->method('save');

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');

        $this->expectException(InvalidRefreshTokenException::class);

        (new CompanyUserTokenRefresher($refreshTokenReader, $userRepository, $revokedTokenRepository, $tokenGenerator))
            ->execute($this->refreshTokenDto('garbage'));
    }

    public function testARefreshTokenForAMissingUserIsRejected(): void
    {
        $this->assertRefreshIsRejectedFor(null);
    }

    public function testARefreshTokenForADeactivatedMemberIsRejected(): void
    {
        $member = new MemberUser(self::USER_ID, $this->company(), '27333444555', 'hashed-password', 'Ana', []);
        $member->deactivate();

        $this->assertRefreshIsRejectedFor($member);
    }

    private function assertRefreshIsRejectedFor(?MemberUser $user): void
    {
        $refreshTokenReader = $this->createMock(RefreshTokenReaderInterface::class);
        $refreshTokenReader->method('read')
            ->willReturn(new RefreshTokenClaims(self::USER_ID, 'a-jti', new \DateTimeImmutable('+1 week')));

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $revokedTokenRepository = $this->createMock(RevokedTokenRepositoryInterface::class);
        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');

        $this->expectException(InvalidRefreshTokenException::class);

        (new CompanyUserTokenRefresher($refreshTokenReader, $userRepository, $revokedTokenRepository, $tokenGenerator))
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
        );
    }

    private function admin(): AdminUser
    {
        return AdminUser::forCompany(self::USER_ID, $this->company(), 'hashed-password');
    }

    private function refreshTokenDto(string $refreshToken): RefreshTokenDto
    {
        $request = new Request([], [], [], [], [], [], json_encode(['refreshToken' => $refreshToken], JSON_THROW_ON_ERROR));

        return new RefreshTokenDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
