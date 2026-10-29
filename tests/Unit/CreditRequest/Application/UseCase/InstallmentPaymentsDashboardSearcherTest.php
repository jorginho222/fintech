<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\InstallmentPaymentsDashboardSearcher;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class InstallmentPaymentsDashboardSearcherTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function testSearchesPendingPaymentsWithinTheCurrentMonthAndOverduePaymentsWithoutBounds(): void
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $monthEnd   = $monthStart->modify('first day of next month');

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findActiveCreditInstallments')
            ->willReturnCallback(static function (string $companyId, InstallmentStatus $status, ?\DateTimeImmutable $dueFrom = null, ?\DateTimeImmutable $dueBefore = null) use ($monthStart, $monthEnd): array {
                self::assertSame(self::COMPANY_ID, $companyId);
                if ($status === InstallmentStatus::Pending) {
                    self::assertEquals($monthStart, $dueFrom);
                    self::assertEquals($monthEnd, $dueBefore);
                } else {
                    self::assertSame(InstallmentStatus::Overdue, $status);
                    self::assertNull($dueFrom);
                    self::assertNull($dueBefore);
                }

                return [];
            });

        $this->searcher($repository)->execute();
    }

    public function testEachTotalSumsItsOwnList(): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findActiveCreditInstallments')->willReturnCallback(
            fn (string $companyId, InstallmentStatus $status): array => $status === InstallmentStatus::Pending
                ? [$this->installment(1, '1000.10'), $this->installment(2, '2000.25')]
                : [$this->installment(3, '500.5000')],
        );

        $payments = $this->searcher($repository)->execute();

        self::assertCount(2, $payments->monthPayments);
        self::assertSame('3000.35', $payments->monthTotalAmount);
        self::assertCount(1, $payments->overduePayments);
        self::assertSame('500.50', $payments->overdueTotalAmount);
    }

    public function testTotalsAreZeroWhenThereIsNothingToPay(): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findActiveCreditInstallments')->willReturn([]);

        $payments = $this->searcher($repository)->execute();

        self::assertSame('0.00', $payments->monthTotalAmount);
        self::assertSame('0.00', $payments->overdueTotalAmount);
    }

    private function searcher(CreditRequestRepositoryInterface $repository): InstallmentPaymentsDashboardSearcher
    {
        $companyIdProvider = $this->createStub(AuthenticatedCompanyIdProviderInterface::class);
        $companyIdProvider->method('getCompanyId')->willReturn(self::COMPANY_ID);

        return new InstallmentPaymentsDashboardSearcher($repository, $companyIdProvider);
    }

    private function installment(int $periodNumber, string $totalAmount): Installment
    {
        $company       = new Company(self::COMPANY_ID, 'Empresa SRL', '20123456780', 'empresa@example.com', TaxStatus::Monotributo);
        $creditRequest = new CreditRequest('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', 'Crédito 1', '10000000', '60.00', 12, $company, CreditRequestStatus::Active);

        return new Installment(
            sprintf('cccccccc-cccc-4ccc-8ccc-%012d', $periodNumber),
            $periodNumber,
            $totalAmount,
            '0.00',
            '0.00',
            $totalAmount,
            InstallmentStatus::Pending,
            $creditRequest,
        );
    }
}
