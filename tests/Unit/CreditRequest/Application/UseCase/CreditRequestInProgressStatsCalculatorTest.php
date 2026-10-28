<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\UseCase\CreditRequestInProgressStatsCalculator;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestInProgressStatsCalculatorTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function testThePercentageIsNullWithoutActiveCreditRequests(): void
    {
        $stats = $this->calculator(0, [])->execute();

        self::assertSame(0, $stats->total);
        self::assertNull($stats->paidPercentage);
    }

    public function testThePercentageIsZeroWhenNoInstallmentIsPaid(): void
    {
        $stats = $this->calculator(2, [
            InstallmentStatus::Pending->value => 3,
            InstallmentStatus::Overdue->value => 1,
        ])->execute();

        self::assertSame(2, $stats->total);
        self::assertSame(0.0, $stats->paidPercentage);
    }

    public function testThePercentageIsAHundredWhenEveryInstallmentIsPaid(): void
    {
        $stats = $this->calculator(1, [InstallmentStatus::Paid->value => 4])->execute();

        self::assertSame(100.0, $stats->paidPercentage);
    }

    public function testThePercentageIsRoundedToTwoDecimals(): void
    {
        $stats = $this->calculator(1, [
            InstallmentStatus::Paid->value    => 1,
            InstallmentStatus::Pending->value => 2,
        ])->execute();

        self::assertSame(33.33, $stats->paidPercentage);
    }

    /**
     * @param array<string, int> $installmentCounts
     */
    private function calculator(int $activeCreditRequests, array $installmentCounts): CreditRequestInProgressStatsCalculator
    {
        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->expects(self::once())->method('countByCompanyAndStatus')
            ->with(self::COMPANY_ID, CreditRequestStatus::Active)
            ->willReturn($activeCreditRequests);
        $repository->expects(self::once())->method('countInstallmentsByStatus')
            ->with(self::COMPANY_ID, CreditRequestStatus::Active)
            ->willReturn($installmentCounts);

        $companyIdProvider = $this->createStub(AuthenticatedCompanyIdProviderInterface::class);
        $companyIdProvider->method('getCompanyId')->willReturn(self::COMPANY_ID);

        return new CreditRequestInProgressStatsCalculator($repository, $companyIdProvider);
    }
}
