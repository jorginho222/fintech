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
            InstallmentStatus::Overdue,
            $creditRequest,
        );
    }
}
