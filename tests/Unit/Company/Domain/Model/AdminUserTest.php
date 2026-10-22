<?php

declare(strict_types=1);

namespace App\Tests\Unit\Company\Domain\Model;

use App\Company\Domain\Model\AdminUser;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\Permission;
use App\Company\Domain\Model\TaxStatus;
use PHPUnit\Framework\TestCase;

final class AdminUserTest extends TestCase
{
    public function testTheAdminLogsInWithTheCompanyCuitAndHoldsEveryPermission(): void
    {
        $company = new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@empresa.com', TaxStatus::Monotributo);

        $admin = AdminUser::forCompany('cccccccc-cccc-4ccc-cccc-cccccccccccc', $company, 'hash');

        self::assertSame('20123456789', $admin->getTaxIdentifier());
        self::assertTrue($admin->isAdmin());
        self::assertTrue($admin->isActive());
        self::assertFalse($admin->mustChangePassword());
        self::assertSame(Permission::cases(), $admin->permissions());
        foreach (Permission::cases() as $permission) {
            self::assertTrue($admin->hasPermission($permission));
        }
    }
}
