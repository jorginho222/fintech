<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestPaidChecker;
use App\CreditRequest\Application\UseCase\InstallmentToPaidUpdater;
use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class InstallmentToPaidUpdaterTest extends TestCase
{
    public function testDoesNotFindInstallmentBelongingToAnotherCompany(): void
    {
        $installment = $this->createInstallment();

        $creditRequestRepository = $this->createMock(CreditRequestRepositoryInterface::class);
        $creditRequestRepository->expects(self::once())
            ->method('findInstallmentById')
            ->with($installment->getId())
            ->willReturn($installment);
        $creditRequestRepository->expects(self::never())->method('saveInstallment');
        $creditRequestRepository->expects(self::never())->method('save');

        $authenticatedCompanyIdProvider = $this->createMock(AuthenticatedCompanyIdProviderInterface::class);
        $authenticatedCompanyIdProvider->expects(self::once())
            ->method('getCompanyId')
            ->willReturn('dddddddd-dddd-4ddd-dddd-dddddddddddd');

        $updater = new InstallmentToPaidUpdater(
            $creditRequestRepository,
            new CreditRequestPaidChecker($creditRequestRepository),
            $authenticatedCompanyIdProvider,
        );

        $this->expectException(InstallmentNotFoundException::class);

        $updater->execute($installment->getId());
    }

    private function createInstallment(): Installment
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
            'hashed-password',
        );

        $creditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Active,
        );

        return new Installment(
            'cccccccc-cccc-4ccc-cccc-cccccccccccc',
            1,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.33',
            InstallmentStatus::Pending,
            $creditRequest,
        );
    }
}
