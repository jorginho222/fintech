<?php

declare(strict_types=1);

namespace App\Tests\Integration\Company;

use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    private UserRepositoryInterface $userRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->userRepository = self::getContainer()->get(UserRepositoryInterface::class);
        $this->em             = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAdminsAndMembersRoundTripAsTheirOwnType(): void
    {
        $company = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $this->userRepository->save(AdminUser::forCompany('11111111-1111-4111-8111-111111111111', $company, 'admin-hash'));
        $this->userRepository->save($this->member('22222222-2222-4222-8222-222222222222', $company, '27333444555', [Permission::TotalDebtView, Permission::InstallmentPay]));
        $this->em->clear();

        $admin  = $this->userRepository->findByTaxIdentifier('20123456789');
        $member = $this->userRepository->findById('22222222-2222-4222-8222-222222222222');

        self::assertInstanceOf(AdminUser::class, $admin);
        self::assertSame('admin-hash', $admin->getHashedPassword());
        self::assertInstanceOf(MemberUser::class, $member);
        self::assertSame('Ana', $member->getDisplayName());
        self::assertSame([Permission::TotalDebtView, Permission::InstallmentPay], $member->permissions());
        self::assertTrue($member->mustChangePassword());
    }

    public function testATaxIdentifierIsUniqueAcrossAdminsAndMembers(): void
    {
        $companyA = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $companyB = $this->company('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '30987654321');
        $this->userRepository->save(AdminUser::forCompany('11111111-1111-4111-8111-111111111111', $companyA, 'hash'));

        $this->expectException(UniqueConstraintViolationException::class);

        // Company B's member with company A's CUIT as CUIL (a sole proprietor's CUIT is their CUIL).
        $this->userRepository->save($this->member('22222222-2222-4222-8222-222222222222', $companyB, '20123456789'));
    }

    public function testMemberLookupsAreScopedToTheCompanyAndSkipTheAdmin(): void
    {
        $companyA = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $companyB = $this->company('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '30987654321');
        $this->userRepository->save(AdminUser::forCompany('11111111-1111-4111-8111-111111111111', $companyA, 'hash'));
        $this->userRepository->save($this->member('22222222-2222-4222-8222-222222222222', $companyA, '27333444555'));
        $this->userRepository->save($this->member('33333333-3333-4333-8333-333333333333', $companyB, '27666777888'));

        self::assertNotNull($this->userRepository->findMemberByIdAndCompany('22222222-2222-4222-8222-222222222222', $companyA->getId()));
        self::assertNull($this->userRepository->findMemberByIdAndCompany('33333333-3333-4333-8333-333333333333', $companyA->getId()));
        self::assertNull($this->userRepository->findMemberByIdAndCompany('11111111-1111-4111-8111-111111111111', $companyA->getId()));

        $members = $this->userRepository->findMembersByCompany($companyA->getId());
        self::assertCount(1, $members);
        self::assertSame('22222222-2222-4222-8222-222222222222', $members[0]->getId());
    }

    public function testOnlyActiveMembersCountTowardsTheLimit(): void
    {
        $company = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $this->userRepository->save(AdminUser::forCompany('11111111-1111-4111-8111-111111111111', $company, 'hash'));
        $this->userRepository->save($this->member('22222222-2222-4222-8222-222222222222', $company, '27333444555'));
        $inactive = $this->member('33333333-3333-4333-8333-333333333333', $company, '27666777888');
        $inactive->deactivate();
        $this->userRepository->save($inactive);

        self::assertSame(1, $this->userRepository->countActiveMembersByCompany($company->getId()));
    }

    private function company(string $id, string $cuit): Company
    {
        $company = new Company($id, 'Empresa ' . $cuit, $cuit, 'empresa' . $cuit . '@empresa.com', TaxStatus::Monotributo);
        self::getContainer()->get(CompanyRepositoryInterface::class)->save($company);

        return $company;
    }

    /**
     * @param Permission[] $permissions
     */
    private function member(string $id, Company $company, string $cuil, array $permissions = []): MemberUser
    {
        return new MemberUser($id, $company, $cuil, 'member-hash', 'Ana', $permissions);
    }
}
