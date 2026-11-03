<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Domain\Model;

use App\CreditRequest\Domain\Exception\InstallmentAlreadyPaidException;
use App\CreditRequest\Domain\Exception\InstallmentPaymentInProcessException;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstallmentTest extends TestCase
{
    use InstallmentFixture;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-02 10:00:00');
    }

    public function testHasNoPaymentAttemptUntilAPaymentIsRequested(): void
    {
        $installment = $this->installment();

        self::assertNull($installment->getLastPaymentAttemptStatus());
        self::assertNull($installment->getLastPaymentAttemptAt());
        self::assertNull($installment->getLastPaymentAttemptErrorMessage());
    }

    /**
     * @return iterable<string, array{InstallmentStatus, callable(Installment, \DateTimeImmutable): mixed}>
     */
    public static function payableInstallments(): iterable
    {
        $noAttempt = static fn (): null => null;
        $rejected  = static function (Installment $installment, \DateTimeImmutable $now): void {
            $installment->startPaymentAttempt($now);
            $installment->rejectPaymentAttempt($now);
        };
        $failed = static function (Installment $installment, \DateTimeImmutable $now): void {
            $installment->startPaymentAttempt($now);
            $installment->failPaymentAttempt($now, 'Gateway error');
        };
        $timedOut = static function (Installment $installment, \DateTimeImmutable $now): void {
            $installment->startPaymentAttempt($now);
            $installment->timeoutPaymentAttempt($now);
        };

        yield 'pending, no attempt' => [InstallmentStatus::Pending, $noAttempt];
        yield 'overdue, no attempt' => [InstallmentStatus::Overdue, $noAttempt];
        yield 'pending, rejected' => [InstallmentStatus::Pending, $rejected];
        yield 'overdue, rejected' => [InstallmentStatus::Overdue, $rejected];
        yield 'pending, processing failed' => [InstallmentStatus::Pending, $failed];
        yield 'pending, timed out' => [InstallmentStatus::Pending, $timedOut];
    }

    #[DataProvider('payableInstallments')]
    public function testStartingAPaymentAttemptSetsProcessingWithoutChangingTheStatus(InstallmentStatus $status, callable $previousAttempt): void
    {
        $installment = $this->installment($status);
        $previousAttempt($installment, $this->now->modify('-1 day'));

        $installment->startPaymentAttempt($this->now);

        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $installment->getLastPaymentAttemptStatus());
        self::assertEquals($this->now, $installment->getLastPaymentAttemptAt());
        self::assertNull($installment->getLastPaymentAttemptErrorMessage());
        self::assertSame($status, $installment->getStatus());
    }

    public function testCannotStartAPaymentAttemptWhileAnotherIsProcessing(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);

        $this->expectException(InstallmentPaymentInProcessException::class);

        $installment->startPaymentAttempt($this->now);
    }

    public function testCannotStartAPaymentAttemptOnAPaidInstallment(): void
    {
        $installment = $this->installment(InstallmentStatus::Paid);

        $this->expectException(InstallmentAlreadyPaidException::class);

        $installment->startPaymentAttempt($this->now);
    }

    public function testApprovingPaysTheInstallment(): void
    {
        $installment = $this->installment(InstallmentStatus::Overdue);
        $installment->startPaymentAttempt($this->now->modify('-1 hour'));

        self::assertTrue($installment->approvePaymentAttempt($this->now));

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $installment->getLastPaymentAttemptStatus());
        self::assertEquals($this->now, $installment->getLastPaymentAttemptAt());
    }

    public function testALateApprovalAfterTheTimeoutStillPaysTheInstallment(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);
        $installment->timeoutPaymentAttempt($this->now);

        self::assertTrue($installment->approvePaymentAttempt($this->now));

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
    }

    public function testRejectingKeepsTheStatus(): void
    {
        $installment = $this->installment(InstallmentStatus::Overdue);
        $installment->startPaymentAttempt($this->now);

        self::assertTrue($installment->rejectPaymentAttempt($this->now));

        self::assertSame(InstallmentStatus::Overdue, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Rejected, $installment->getLastPaymentAttemptStatus());
    }

    public function testFailingStoresTheErrorMessageAndKeepsTheStatus(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);

        self::assertTrue($installment->failPaymentAttempt($this->now, 'Gateway error'));

        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::ProcessingFailed, $installment->getLastPaymentAttemptStatus());
        self::assertSame('Gateway error', $installment->getLastPaymentAttemptErrorMessage());
    }

    public function testALateResultReplacesTheStoredErrorMessage(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);
        $installment->failPaymentAttempt($this->now, 'Gateway error');
        $installment->startPaymentAttempt($this->now);
        $installment->timeoutPaymentAttempt($this->now);

        self::assertNull($installment->getLastPaymentAttemptErrorMessage());
    }

    public function testResultsAreIgnoredWithoutAnAttempt(): void
    {
        $installment = $this->installment();

        self::assertFalse($installment->approvePaymentAttempt($this->now));
        self::assertFalse($installment->rejectPaymentAttempt($this->now));
        self::assertFalse($installment->failPaymentAttempt($this->now, 'Gateway error'));

        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertNull($installment->getLastPaymentAttemptStatus());
    }

    public function testResultsAreIgnoredOnceThePaymentWasApproved(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);
        $installment->approvePaymentAttempt($this->now);

        self::assertFalse($installment->approvePaymentAttempt($this->now));
        self::assertFalse($installment->rejectPaymentAttempt($this->now));

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $installment->getLastPaymentAttemptStatus());
    }

    public function testOnlyAProcessingAttemptCanTimeOut(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);
        $installment->rejectPaymentAttempt($this->now);

        $installment->timeoutPaymentAttempt($this->now);

        self::assertSame(InstallmentPaymentAttemptStatus::Rejected, $installment->getLastPaymentAttemptStatus());
    }

    public function testIsNotMarkedOverdueWhileThePaymentIsProcessing(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);

        $installment->markOverdue();

        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
    }

    public function testIsMarkedOverdueAfterARejectionKeepingTheAttempt(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt($this->now);
        $installment->rejectPaymentAttempt($this->now);

        $installment->markOverdue();

        self::assertSame(InstallmentStatus::Overdue, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Rejected, $installment->getLastPaymentAttemptStatus());
    }
}
