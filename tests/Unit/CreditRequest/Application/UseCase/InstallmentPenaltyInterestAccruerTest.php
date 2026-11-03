<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestAccruer;
use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestUpdater;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;

final class InstallmentPenaltyInterestAccruerTest extends TestCase
{
    use InstallmentFixture;

    public function testSavesOnlyTheInstallmentsCharged(): void
    {
        $now = new \DateTimeImmutable('2026-11-20 03:00:00');
        $charged = $this->installment(InstallmentStatus::Overdue, periodNumber: 1);
        $alreadyCalculatedToday = $this->installment(InstallmentStatus::Overdue, periodNumber: 2);
        $alreadyCalculatedToday->setLastPenaltyCalculationAt($now);

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')
            ->with(new InstallmentSearchCriteria(
                creditRequestStatus: CreditRequestStatus::Active,
                status: InstallmentStatus::Overdue,
                excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            ))
            ->willReturn([$charged, $alreadyCalculatedToday]);
        $repository->expects(self::once())->method('saveInstallment')->with($charged);

        $count = (new InstallmentPenaltyInterestAccruer($repository, new InstallmentPenaltyInterestUpdater()))->execute($now);

        self::assertSame(1, $count);
    }
}
