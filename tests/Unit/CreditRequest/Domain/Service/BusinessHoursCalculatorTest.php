<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Domain\Service;

use App\CreditRequest\Domain\Service\BusinessHoursCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BusinessHoursCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function seventyTwoHours(): iterable
    {
        yield 'monday stays within the week' => ['2026-10-26 10:00:00', '2026-10-29 10:00:00'];
        yield 'wednesday skips the weekend' => ['2026-10-28 10:00:00', '2026-11-02 10:00:00'];
        yield 'friday skips the weekend' => ['2026-10-30 10:00:00', '2026-11-04 10:00:00'];
        yield 'saturday counts from monday' => ['2026-10-31 15:00:00', '2026-11-05 00:00:00'];
        yield 'sunday counts from monday' => ['2026-11-01 23:59:00', '2026-11-05 00:00:00'];
    }

    #[DataProvider('seventyTwoHours')]
    public function testCountsOnlyMondayToFriday(string $from, string $expected): void
    {
        $limit = (new BusinessHoursCalculator())->addBusinessHours(new \DateTimeImmutable($from), 72);

        self::assertSame($expected, $limit->format('Y-m-d H:i:s'));
    }
}
