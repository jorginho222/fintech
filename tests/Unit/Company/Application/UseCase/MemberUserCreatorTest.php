<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\DTO\MemberUserCreateDto;
use App\Company\Application\UseCase\MemberUserCreator;
use App\Company\Domain\Exception\ActiveUserLimitReachedException;
use App\Company\Domain\Exception\UserTaxIdentifierAlreadyTakenException;
use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\PasswordHasherInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class MemberUserCreatorTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const string CUIL       = '27333444555';

    public function testItCreatesAnActiveMemberThatMustChangeItsPassword(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByTaxIdentifier')->willReturn(null);
        $userRepository->method('countActiveMembersByCompany')->willReturn(MemberUser::MAX_ACTIVE_PER_COMPANY - 1);
        $userRepository->expects(self::once())->method('save')->with(self::isInstanceOf(MemberUser::class));

        $member = $this->creator($userRepository)->execute($this->dto());

        self::assertSame(self::CUIL, $member->getTaxIdentifier());
        self::assertSame('Ana Pérez', $member->getDisplayName());
        self::assertSame('hashed:Secret123!', $member->getHashedPassword());
        self::assertSame([Permission::TotalDebtView], $member->permissions());
        self::assertTrue($member->isActive());
        self::assertTrue($member->mustChangePassword());
        self::assertSame(self::COMPANY_ID, $member->getCompany()->getId());
    }

    public function testATaxIdentifierAlreadyUsedByAnyUserIsRejected(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByTaxIdentifier')
            ->willReturn(AdminUser::forCompany('cccccccc-cccc-4ccc-cccc-cccccccccccc', $this->company(), 'hash'));
        $userRepository->expects(self::never())->method('save');

        $this->expectException(UserTaxIdentifierAlreadyTakenException::class);

        $this->creator($userRepository)->execute($this->dto());
    }

    public function testTheEleventhActiveMemberIsRejected(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByTaxIdentifier')->willReturn(null);
        $userRepository->method('countActiveMembersByCompany')->willReturn(MemberUser::MAX_ACTIVE_PER_COMPANY);
        $userRepository->expects(self::never())->method('save');

        $this->expectException(ActiveUserLimitReachedException::class);

        $this->creator($userRepository)->execute($this->dto());
    }

    private function creator(UserRepositoryInterface $userRepository): MemberUserCreator
    {
        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('findById')->with(self::COMPANY_ID)->willReturn($this->company());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturnCallback(static fn (string $plain): string => 'hashed:' . $plain);

        $companyIdProvider = $this->createMock(AuthenticatedCompanyIdProviderInterface::class);
        $companyIdProvider->method('getCompanyId')->willReturn(self::COMPANY_ID);

        return new MemberUserCreator($userRepository, $companyRepository, $hasher, $companyIdProvider);
    }

    private function company(): Company
    {
        return new Company(self::COMPANY_ID, 'Empresa SRL', '20123456789', 'empresa@empresa.com', TaxStatus::Monotributo);
    }

    private function dto(): MemberUserCreateDto
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'id'            => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'taxIdentifier' => self::CUIL,
            'displayName'   => '  Ana Pérez ',
            'password'      => 'Secret123!',
            'permissions'   => [Permission::TotalDebtView->value],
        ], JSON_THROW_ON_ERROR));

        return new MemberUserCreateDto($request, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
