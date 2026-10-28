<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\CreditRequestApplicationStatsCalculator;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestApplicationStatsCalculatorTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function testFillsMissingStatusesWithZero(): void
    {
        $stats = $this->calculator([CreditRequestApplicationStatus::Approved->value => 4])->execute();

        self::assertSame(4, $stats->total);
        self::assertSame(4, $stats->approved);
        self::assertSame(0, $stats->rejected);
        self::assertSame(0, $stats->evaluationPending);
    }

    /**
     * Guard: fails when a status is added to the enum without extending the stats breakdown.
     */
    public function testTheBreakdownCoversEveryApplicationStatus(): void
    {
        $counts = [];
        foreach (CreditRequestApplicationStatus::cases() as $status) {
            $counts[$status->value] = 1;
        }

        $stats = $this->calculator($counts)->execute();

        self::assertSame(count(CreditRequestApplicationStatus::cases()), $stats->total);
        self::assertSame($stats->total, $stats->approved + $stats->rejected + $stats->evaluationPending);
    }

    /**
     * @param array<string, int> $counts
     */
    private function calculator(array $counts): CreditRequestApplicationStatsCalculator
    {
        $repository = $this->createMock(CreditRequestApplicationRepositoryInterface::class);
        $repository->expects(self::once())->method('countByStatus')->with(self::COMPANY_ID)->willReturn($counts);

        $companyIdProvider = $this->createStub(AuthenticatedCompanyIdProviderInterface::class);
        $companyIdProvider->method('getCompanyId')->willReturn(self::COMPANY_ID);

        return new CreditRequestApplicationStatsCalculator($repository, $companyIdProvider);
    }
}
