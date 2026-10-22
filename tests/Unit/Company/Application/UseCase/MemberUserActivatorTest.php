<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Application\UseCase;

use App\Company\Application\UseCase\MemberUserActivator;
use App\Company\Application\UseCase\MemberUserFinder;
use App\Company\Domain\Exception\ActiveUserLimitReachedException;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class MemberUserActivatorTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const string MEMBER_ID  = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    public function testADeactivatedMemberIsActivatedWhileThereIsRoom(): void
    {
        $member = $this->member(active: false);

        $userRepository = $this->repositoryReturning($member, activeMembers: MemberUser::MAX_ACTIVE_PER_COMPANY - 1);
        $userRepository->expects(self::once())->method('save')->with($member);

        $this->activator($userRepository)->execute(self::MEMBER_ID);

        self::assertTrue($member->isActive());
    }

    public function testActivatingBeyondTheLimitIsRejected(): void
    {
        $member = $this->member(active: false);

        $userRepository = $this->repositoryReturning($member, activeMembers: MemberUser::MAX_ACTIVE_PER_COMPANY);
        $userRepository->expects(self::never())->method('save');

        try {
            $this->activator($userRepository)->execute(self::MEMBER_ID);
            self::fail('Expected the active user limit to be enforced.');
        } catch (ActiveUserLimitReachedException) {
            self::assertFalse($member->isActive());
        }
    }

    public function testActivatingAnAlreadyActiveMemberIsANoOpEvenAtTheLimit(): void
    {
        $member = $this->member(active: true);

        $userRepository = $this->repositoryReturning($member, activeMembers: MemberUser::MAX_ACTIVE_PER_COMPANY);
        $userRepository->expects(self::never())->method('countActiveMembersByCompany');
        $userRepository->expects(self::never())->method('save');

        self::assertSame($member, $this->activator($userRepository)->execute(self::MEMBER_ID));
    }

    private function repositoryReturning(MemberUser $member, int $activeMembers): UserRepositoryInterface
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findMemberByIdAndCompany')->with(self::MEMBER_ID, self::COMPANY_ID)->willReturn($member);
        $userRepository->method('countActiveMembersByCompany')->willReturn($activeMembers);

        return $userRepository;
    }

    private function activator(UserRepositoryInterface $userRepository): MemberUserActivator
    {
        $companyIdProvider = $this->createMock(AuthenticatedCompanyIdProviderInterface::class);
        $companyIdProvider->method('getCompanyId')->willReturn(self::COMPANY_ID);

        return new MemberUserActivator(new MemberUserFinder($userRepository, $companyIdProvider), $userRepository);
    }

    private function member(bool $active): MemberUser
    {
        $company = new Company(self::COMPANY_ID, 'Empresa SRL', '20123456789', 'empresa@empresa.com', TaxStatus::Monotributo);
        $member  = new MemberUser(self::MEMBER_ID, $company, '27333444555', 'hash', 'Ana', []);
        if (!$active) {
            $member->deactivate();
        }

        return $member;
    }
}
