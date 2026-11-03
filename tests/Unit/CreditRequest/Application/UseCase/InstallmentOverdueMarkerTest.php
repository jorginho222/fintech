<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\InstallmentOverdueMarker;
use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestUpdater;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;

final class InstallmentOverdueMarkerTest extends TestCase
{
    use InstallmentFixture;

    public function testMarksAndNotifiesEachInstallmentFoundExcludingProcessingPayments(): void
    {
        $now = new \DateTimeImmutable('2026-11-02 10:00:00');
        $installment = $this->installment();

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')
            ->with(new InstallmentSearchCriteria(
                status: InstallmentStatus::Pending,
                excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
                dueBefore: $now,
            ))
            ->willReturn([$installment]);
        $repository->expects(self::once())->method('saveInstallment')->with($installment);

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyInstallmentOverdue')->with($installment);

        $count = (new InstallmentOverdueMarker($repository, new InstallmentPenaltyInterestUpdater(), $notifier))->execute($now);

        self::assertSame(1, $count);
        self::assertSame(InstallmentStatus::Overdue, $installment->getStatus());
    }
}
