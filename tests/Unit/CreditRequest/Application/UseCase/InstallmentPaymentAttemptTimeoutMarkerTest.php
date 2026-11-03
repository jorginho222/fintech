<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\InstallmentPaymentAttemptTimeoutMarker;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\BusinessHoursCalculator;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;

final class InstallmentPaymentAttemptTimeoutMarkerTest extends TestCase
{
    use InstallmentFixture;

    public function testTimesOutOnlyAttemptsPastTheLimitAndNotifiesThem(): void
    {
        // Submitted on a Friday at 10:00: the limit is the next Wednesday at 10:00.
        $submittedAt = new \DateTimeImmutable('2026-10-30 10:00:00');
        $expired = $this->installment(periodNumber: 1);
        $expired->startPaymentAttempt($submittedAt);
        $recent = $this->installment(periodNumber: 2);
        $recent->startPaymentAttempt($submittedAt->modify('+1 minute'));
        $now = new \DateTimeImmutable('2026-11-04 10:00:00');

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')
            ->with(new InstallmentSearchCriteria(paymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing))
            ->willReturn([$expired, $recent]);
        $repository->expects(self::once())->method('saveInstallment')->with($expired);

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyPaymentProcessingTimeout')->with($expired);

        $count = (new InstallmentPaymentAttemptTimeoutMarker($repository, new BusinessHoursCalculator(), $notifier))->execute($now);

        self::assertSame(1, $count);
        self::assertSame(InstallmentPaymentAttemptStatus::ProcessingTimeout, $expired->getLastPaymentAttemptStatus());
        self::assertEquals($now, $expired->getLastPaymentAttemptAt());
        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $recent->getLastPaymentAttemptStatus());
    }
}
