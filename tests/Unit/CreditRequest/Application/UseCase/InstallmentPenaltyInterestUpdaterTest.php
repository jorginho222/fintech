<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\InstallmentPenaltyInterestUpdater;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use PHPUnit\Framework\TestCase;

final class InstallmentPenaltyInterestUpdaterTest extends TestCase
{
    private InstallmentPenaltyInterestUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new InstallmentPenaltyInterestUpdater();
    }

    public function testAccruesOneDayOfInterestOnFirstCall(): void
    {
        $installment = $this->createInstallment();

        $accrued = $this->updater->execute($installment, new \DateTimeImmutable('2026-07-14'));

        self::assertTrue($accrued);
        self::assertSame('2054.79', $installment->getPenaltyInterestAmount());
        self::assertSame('431.51', $installment->getPenaltyIva21Tax());
        self::assertSame('896319.63', $installment->getTotalAmount());
        self::assertEquals(new \DateTimeImmutable('2026-07-14'), $installment->getLastPenaltyCalculationAt());
    }

    public function testAccumulatesAcrossDifferentDays(): void
    {
        $installment = $this->createInstallment();

        $this->updater->execute($installment, new \DateTimeImmutable('2026-07-14'));
        $this->updater->execute($installment, new \DateTimeImmutable('2026-07-15'));

        self::assertSame('4109.58', $installment->getPenaltyInterestAmount());
        self::assertSame('863.02', $installment->getPenaltyIva21Tax());
    }

    public function testIsANoOpWhenAlreadyCalculatedToday(): void
    {
        $installment = $this->createInstallment();

        $this->updater->execute($installment, new \DateTimeImmutable('2026-07-14 08:00:00'));
        $accruedAgain = $this->updater->execute($installment, new \DateTimeImmutable('2026-07-14 20:00:00'));

        self::assertFalse($accruedAgain);
        self::assertSame('2054.79', $installment->getPenaltyInterestAmount());
        self::assertSame('431.51', $installment->getPenaltyIva21Tax());
    }

    public function testChargesEveryDaySinceTheLastCalculationLikeRunningOncePerDay(): void
    {
        $dailyRuns = $this->createInstallment();
        foreach (['2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17'] as $day) {
            $this->updater->execute($dailyRuns, new \DateTimeImmutable($day));
        }

        $missedRuns = $this->createInstallment();
        $this->updater->execute($missedRuns, new \DateTimeImmutable('2026-07-14'));
        $accrued = $this->updater->execute($missedRuns, new \DateTimeImmutable('2026-07-17'));

        self::assertTrue($accrued);
        self::assertSame($dailyRuns->getPenaltyInterestAmount(), $missedRuns->getPenaltyInterestAmount());
        self::assertSame($dailyRuns->getPenaltyIva21Tax(), $missedRuns->getPenaltyIva21Tax());
        self::assertSame($dailyRuns->getTotalAmount(), $missedRuns->getTotalAmount());
        self::assertEquals(new \DateTimeImmutable('2026-07-17'), $missedRuns->getLastPenaltyCalculationAt());
    }

    public function testCountsFromTheDueDateWhenNeverCalculated(): void
    {
        $installment = $this->createInstallment();
        $installment->changeDueDate(new \DateTimeImmutable('2026-07-11'));

        $this->updater->execute($installment, new \DateTimeImmutable('2026-07-14 03:00:00'));

        // Three days of the 2054.79 charged per day.
        self::assertSame('6164.37', $installment->getPenaltyInterestAmount());
    }

    public function testChargesNothingBeforeTheDueDateHasPassed(): void
    {
        $installment = $this->createInstallment();
        $installment->changeDueDate(new \DateTimeImmutable('2026-07-14'));

        self::assertFalse($this->updater->execute($installment, new \DateTimeImmutable('2026-07-14 03:00:00')));
        self::assertFalse($this->updater->execute($installment, new \DateTimeImmutable('2026-07-13')));
        self::assertSame('0.0000', $installment->getPenaltyInterestAmount());
    }

    private function createInstallment(): Installment
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );

        $creditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Crédito 1',
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
            InstallmentStatus::Overdue,
            $creditRequest,
        );
    }
}
