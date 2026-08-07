<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Service\InstallmentCalculator;
use App\CreditRequest\Domain\Service\InstallmentCreateDto;
use PHPUnit\Framework\TestCase;

final class InstallmentCalculatorTest extends TestCase
{
    private InstallmentCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InstallmentCalculator();
    }

    public function testSinglePeriodInstallmentAbsorbsFullPrincipalAndInterest(): void
    {
        $installments = $this->calculator->calculate('100000', 1, '0.60');

        self::assertCount(1, $installments);

        $installment = $installments[0];
        self::assertSame(1, $installment->periodNumber);
        self::assertSame('100000.00', $installment->capitalAmount);
        self::assertSame('5000.00', $installment->interestAmount);
        self::assertSame('1050.00', $installment->taxOnInterestAmount);
        self::assertSame('106050.00', $installment->totalAmount);
    }

    public function testTwoPeriodInstallmentSchedule(): void
    {
        // Reference values independently computed with the documented French
        // amortization formula (amount=100000, i=5% monthly, IVA=21%).
        $installments = $this->calculator->calculate('100000', 2, '0.60');

        self::assertCount(2, $installments);

        [$first, $second] = $installments;

        self::assertSame(1, $first->periodNumber);
        self::assertSame('48780.49', $first->capitalAmount);
        self::assertSame('5000.00', $first->interestAmount);
        self::assertSame('1050.00', $first->taxOnInterestAmount);
        self::assertSame('54830.49', $first->totalAmount);

        self::assertSame(2, $second->periodNumber);
        self::assertSame('51219.51', $second->capitalAmount);
        self::assertSame('2560.98', $second->interestAmount);
        self::assertSame('537.81', $second->taxOnInterestAmount);
        self::assertSame('54318.30', $second->totalAmount);
    }

    public function testFirstPeriodInterestIsAlwaysComputedOverTheFullRequestedAmount(): void
    {
        $installments = $this->calculator->calculate('200000', 6, '0.60');

        // interest_1 = requestedAmount * monthlyRate = 200000 * 0.05, independent of the term
        self::assertSame('10000.00', $installments[0]->interestAmount);
        self::assertSame('2100.00', $installments[0]->taxOnInterestAmount);
    }

    public function testCapitalAmountsAddUpToTheRequestedAmount(): void
    {
        $requestedAmount = '150000';
        $installments = $this->calculator->calculate($requestedAmount, 12, '0.60');

        $capitalSum = array_reduce(
            $installments,
            static fn (string $carry, InstallmentCreateDto $installment): string => bcadd($carry, $installment->capitalAmount, 2),
            '0',
        );

        self::assertSame(bcadd($requestedAmount, '0', 2), $capitalSum);
    }

    public function testReturnsOnePeriodPerRequestedTermInSequentialOrder(): void
    {
        $installments = $this->calculator->calculate('100000', 12, '0.60');

        self::assertCount(12, $installments);
        foreach ($installments as $index => $installment) {
            self::assertSame($index + 1, $installment->periodNumber);
        }
    }

    public function testTotalAmountIsTheSumOfItsComponents(): void
    {
        $installments = $this->calculator->calculate('100000', 12, '0.60');

        foreach ($installments as $installment) {
            $expectedTotal = bcadd(
                bcadd($installment->capitalAmount, $installment->interestAmount, 2),
                $installment->taxOnInterestAmount,
                2,
            );

            self::assertSame($expectedTotal, $installment->totalAmount);
        }
    }
}
