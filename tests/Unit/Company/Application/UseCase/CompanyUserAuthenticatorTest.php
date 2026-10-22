<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyUserLoginDto;
use App\Company\Application\UseCase\CompanyUserAuthenticator;
use App\Company\Domain\Exception\InvalidCredentialsException;
use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class CompanyUserAuthenticatorTest extends TestCase
{
    private const string CUIT = '20123456789';
    private const string CUIL = '27333444555';

    public function testItReturnsTokensTheUserAndItsCompanyWhenCredentialsMatch(): void
    {
        $admin        = $this->admin();
        $accessToken  = new AuthToken('an.access.token', new \DateTimeImmutable('+1 hour'));
        $refreshToken = new AuthToken('a.refresh.token', new \DateTimeImmutable('+1 week'));

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByTaxIdentifier')->with(self::CUIT)->willReturn($admin);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('verify')
            ->with('hashed-password', 'secret123')
            ->willReturn(true);

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::once())->method('generateAccessTokenFor')->with($admin)->willReturn($accessToken);
        $tokenGenerator->expects(self::once())->method('generateRefreshTokenFor')->with($admin)->willReturn($refreshToken);

        $result = (new CompanyUserAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto(self::CUIT, 'secret123'));

        self::assertSame($admin, $result->user);
        self::assertSame($admin->getCompany(), $result->company);
        self::assertSame($accessToken, $result->accessToken);
        self::assertSame($refreshToken, $result->refreshToken);
    }

    public function testItRejectsAWrongPasswordWithoutIssuingAToken(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByTaxIdentifier')->willReturn($this->admin());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('verify')->willReturn(false);

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');
        $tokenGenerator->expects(self::never())->method('generateRefreshTokenFor');

        $this->expectException(InvalidCredentialsException::class);

        (new CompanyUserAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto(self::CUIT, 'wrongpassword1'));
    }

    public function testItRejectsAnUnknownTaxIdentifierWithoutCheckingAPassword(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByTaxIdentifier')->willReturn(null);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('verify');

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');
        $tokenGenerator->expects(self::never())->method('generateRefreshTokenFor');

        $this->expectException(InvalidCredentialsException::class);

        (new CompanyUserAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto('20999999999', 'secret123'));
    }

    public function testItRejectsADeactivatedMemberEvenWithTheRightPassword(): void
    {
        $member = new MemberUser('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', $this->company(), self::CUIL, 'hashed-password', 'Ana', []);
        $member->deactivate();

        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('findByTaxIdentifier')->willReturn($member);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('verify')->willReturn(true);

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');

        $this->expectException(InvalidCredentialsException::class);

        (new CompanyUserAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto(self::CUIL, 'secret123'));
    }

    public function testInvalidCredentialsAreReportedAs401(): void
    {
        self::assertSame(401, (new InvalidCredentialsException())->httpStatusCode());
    }

    private function company(): Company
    {
        return new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            self::CUIT,
            'empresa@empresa.com',
            TaxStatus::Monotributo,
        );
    }

    private function admin(): AdminUser
    {
        return AdminUser::forCompany('cccccccc-cccc-4ccc-cccc-cccccccccccc', $this->company(), 'hashed-password');
    }

    private function loginDto(string $taxIdentifier, string $password): CompanyUserLoginDto
    {
        $request = new Request([], [], [], [], [], [], json_encode(
            ['taxIdentifier' => $taxIdentifier, 'password' => $password],
            JSON_THROW_ON_ERROR,
        ));

        return new CompanyUserLoginDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
