<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Domain\Model;

use App\Company\Domain\Exception\PasswordChangeNotAllowedException;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\MemberUser;
use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\TaxStatus;
use PHPUnit\Framework\TestCase;

final class MemberUserTest extends TestCase
{
    public function testItHoldsOnlyTheGrantedPermissions(): void
    {
        $member = $this->member([Permission::TotalDebtView]);

        self::assertTrue($member->hasPermission(Permission::TotalDebtView));
        self::assertFalse($member->hasPermission(Permission::InstallmentPay));
        self::assertFalse($member->isAdmin());
    }

    public function testReplacingPermissionsDropsDuplicatesAndPreviousGrants(): void
    {
        $member = $this->member([Permission::TotalDebtView]);

        $member->replacePermissions([Permission::InstallmentPay, Permission::CreditRequestList, Permission::InstallmentPay]);

        self::assertSame([Permission::InstallmentPay, Permission::CreditRequestList], $member->permissions());
        self::assertFalse($member->hasPermission(Permission::TotalDebtView));
    }

    public function testDeactivateAndActivateToggleTheStateAndAreHarmlessWhenRepeated(): void
    {
        $member = $this->member();
        self::assertTrue($member->isActive());

        $member->deactivate();
        $member->deactivate();
        self::assertFalse($member->isActive());

        $member->activate();
        $member->activate();
        self::assertTrue($member->isActive());
    }

    public function testANewMemberMustChangeThePasswordOnlyOnce(): void
    {
        $member = $this->member();
        self::assertTrue($member->mustChangePassword());

        $member->changeInitialPassword('new-hash');

        self::assertFalse($member->mustChangePassword());
        self::assertSame('new-hash', $member->getHashedPassword());

        $this->expectException(PasswordChangeNotAllowedException::class);
        $member->changeInitialPassword('another-hash');
    }

    public function testAMalformedCuilIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MemberUser('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', $this->company(), '2733344455', 'hash', 'Ana', []);
    }

    public function testABlankDisplayNameIsRejected(): void
    {
        $member = $this->member();

        $this->expectException(\InvalidArgumentException::class);
        $member->rename('   ');
    }

    /**
     * @param Permission[] $permissions
     */
    private function member(array $permissions = []): MemberUser
    {
        return new MemberUser('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', $this->company(), '27333444555', 'hash', 'Ana', $permissions);
    }

    private function company(): Company
    {
        return new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@empresa.com', TaxStatus::Monotributo);
    }
}
