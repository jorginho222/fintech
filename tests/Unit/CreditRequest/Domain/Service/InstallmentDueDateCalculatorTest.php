<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Service\InstallmentDueDateCalculator;
use PHPUnit\Framework\TestCase;

final class InstallmentDueDateCalculatorTest extends TestCase
{
    private InstallmentDueDateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InstallmentDueDateCalculator();
    }

    public function testFirstDueDateIsThirtyDaysAfterTheGivenDateWhenItIsABusinessDay(): void
    {
        // Thursday
        $from = new \DateTimeImmutable('2026-07-09');

        $dueDates = $this->calculator->calculate($from, 1);

        // +30 days = Saturday 2026-08-08 -> bumped forward to Monday
        self::assertSame('2026-08-10', $dueDates[0]->format('Y-m-d'));
    }

    public function testDueDateFallingOnSaturdayMovesToMonday(): void
    {
        $from = new \DateTimeImmutable('2026-07-09');

        $dueDates = $this->calculator->calculate($from, 1);

        self::assertSame('Monday', $dueDates[0]->format('l'));
    }

    public function testDueDateFallingOnSundayMovesToMonday(): void
    {
        // Friday; +30 days lands on Sunday 2026-08-09
        $from = new \DateTimeImmutable('2026-07-10');

        $dueDates = $this->calculator->calculate($from, 1);

        self::assertSame('2026-08-10', $dueDates[0]->format('Y-m-d'));
    }

    public function testEachFollowingDueDateIsChainedThirtyDaysFromThePreviousAdjustedDate(): void
    {
        $from = new \DateTimeImmutable('2026-07-10');

        $dueDates = $this->calculator->calculate($from, 3);

        self::assertCount(3, $dueDates);
        // installment 1: +30d = Sat 2026-08-08 -> Mon 2026-08-10
        self::assertSame('2026-08-10', $dueDates[0]->format('Y-m-d'));
        // installment 2: +30d from installment 1's adjusted date = Wed 2026-09-09
        self::assertSame('2026-09-09', $dueDates[1]->format('Y-m-d'));
        // installment 3: +30d from installment 2 = Fri 2026-10-09
        self::assertSame('2026-10-09', $dueDates[2]->format('Y-m-d'));
    }

    public function testReturnsOneDueDatePerRequestedInstallmentInAscendingOrder(): void
    {
        $from = new \DateTimeImmutable('2026-07-10');

        $dueDates = $this->calculator->calculate($from, 12);

        self::assertCount(12, $dueDates);
        for ($i = 1; $i < count($dueDates); $i++) {
            self::assertGreaterThan($dueDates[$i - 1], $dueDates[$i]);
        }
    }
}
