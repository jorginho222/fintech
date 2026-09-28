<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyLoginDto;
use App\Company\Application\UseCase\CompanyAuthenticator;
use App\Company\Domain\Exception\InvalidCredentialsException;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class CompanyAuthenticatorTest extends TestCase
{
    private const string CUIT = '20123456789';

    public function testItReturnsAnAccessAndRefreshTokenForTheCompanyWhenCredentialsMatch(): void
    {
        $company      = $this->company();
        $accessToken  = new AuthToken('an.access.token', new \DateTimeImmutable('+1 hour'));
        $refreshToken = new AuthToken('a.refresh.token', new \DateTimeImmutable('+1 week'));

        $repository = $this->createMock(CompanyRepositoryInterface::class);
        $repository->method('findByCuit')->with(self::CUIT)->willReturn($company);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::once())
            ->method('verify')
            ->with('hashed-password', 'secret123')
            ->willReturn(true);

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::once())->method('generateAccessTokenFor')->with($company)->willReturn($accessToken);
        $tokenGenerator->expects(self::once())->method('generateRefreshTokenFor')->with($company)->willReturn($refreshToken);

        $result = (new CompanyAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto(self::CUIT, 'secret123'));

        self::assertSame($company, $result->company);
        self::assertSame($accessToken, $result->accessToken);
        self::assertSame($refreshToken, $result->refreshToken);
    }

    public function testItRejectsAWrongPasswordWithoutIssuingAToken(): void
    {
        $repository = $this->createMock(CompanyRepositoryInterface::class);
        $repository->method('findByCuit')->willReturn($this->company());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('verify')->willReturn(false);

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');
        $tokenGenerator->expects(self::never())->method('generateRefreshTokenFor');

        $this->expectException(InvalidCredentialsException::class);

        (new CompanyAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto(self::CUIT, 'wrongpassword1'));
    }

    public function testItRejectsAnUnknownCuitWithoutCheckingAPassword(): void
    {
        $repository = $this->createMock(CompanyRepositoryInterface::class);
        $repository->method('findByCuit')->willReturn(null);

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->expects(self::never())->method('verify');

        $tokenGenerator = $this->createMock(AuthTokenGeneratorInterface::class);
        $tokenGenerator->expects(self::never())->method('generateAccessTokenFor');
        $tokenGenerator->expects(self::never())->method('generateRefreshTokenFor');

        $this->expectException(InvalidCredentialsException::class);

        (new CompanyAuthenticator($repository, $hasher, $tokenGenerator))
            ->execute($this->loginDto('20999999999', 'secret123'));
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
            'hashed-password',
        );
    }

    private function loginDto(string $cuit, string $password): CompanyLoginDto
    {
        $request = new Request([], [], [], [], [], [], json_encode(
            ['cuit' => $cuit, 'password' => $password],
            JSON_THROW_ON_ERROR,
        ));

        return new CompanyLoginDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
