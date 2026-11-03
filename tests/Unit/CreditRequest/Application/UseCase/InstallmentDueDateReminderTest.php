<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\InstallmentDueDateReminder;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;

final class InstallmentDueDateReminderTest extends TestCase
{
    use InstallmentFixture;

    public function testRemindsPendingInstallmentsDueInThreeDaysSkippingProcessingPayments(): void
    {
        $installment = $this->installment(periodNumber: 1);
        $other = $this->installment(periodNumber: 2);

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')
            ->with(new InstallmentSearchCriteria(
                creditRequestStatus: CreditRequestStatus::Active,
                status: InstallmentStatus::Pending,
                excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
                dueFrom: new \DateTimeImmutable('2026-11-05 00:00:00'),
                dueBefore: new \DateTimeImmutable('2026-11-06 00:00:00'),
            ))
            ->willReturn([$installment, $other]);

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::exactly(2))->method('notifyDueDateReminder');

        $count = (new InstallmentDueDateReminder($repository, $notifier))->execute(new \DateTimeImmutable('2026-11-02 17:30:00'));

        self::assertSame(2, $count);
    }
}
