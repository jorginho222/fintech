<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\InstallmentPaymentsDashboardSearcher;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use PHPUnit\Framework\TestCase;

final class InstallmentPaymentsDashboardSearcherTest extends TestCase
{
    private const string COMPANY_ID = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    public function testSearchesTheThreeListsLeavingProcessingPaymentsOnlyInTheirOwn(): void
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $monthEnd   = $monthStart->modify('first day of next month');
        $criteria   = [];

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->expects(self::exactly(3))->method('searchInstallments')
            ->willReturnCallback(static function (InstallmentSearchCriteria $searched) use (&$criteria): array {
                $criteria[] = $searched;

                return [];
            });

        $this->searcher($repository)->execute();

        self::assertEquals([
            new InstallmentSearchCriteria(
                companyId: self::COMPANY_ID,
                creditRequestStatus: CreditRequestStatus::Active,
                status: InstallmentStatus::Pending,
                excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
                dueFrom: $monthStart,
                dueBefore: $monthEnd,
            ),
            new InstallmentSearchCriteria(
                companyId: self::COMPANY_ID,
                creditRequestStatus: CreditRequestStatus::Active,
                status: InstallmentStatus::Overdue,
                excludedPaymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            ),
            new InstallmentSearchCriteria(
                companyId: self::COMPANY_ID,
                creditRequestStatus: CreditRequestStatus::Active,
                paymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing,
            ),
        ], $criteria);
    }

    public function testEachTotalSumsItsOwnList(): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')->willReturnCallback(
            fn (InstallmentSearchCriteria $criteria): array => match (true) {
                $criteria->paymentAttemptStatus === InstallmentPaymentAttemptStatus::Processing => [$this->installment(4, '700.00')],
                $criteria->status === InstallmentStatus::Pending => [$this->installment(1, '1000.10'), $this->installment(2, '2000.25')],
                default => [$this->installment(3, '500.5000')],
            },
        );

        $payments = $this->searcher($repository)->execute();

        self::assertCount(2, $payments->monthPayments);
        self::assertSame('3000.35', $payments->monthTotalAmount);
        self::assertCount(1, $payments->overduePayments);
        self::assertSame('500.50', $payments->overdueTotalAmount);
        self::assertCount(1, $payments->processingPayments);
        self::assertSame('700.00', $payments->processingTotalAmount);
    }

    public function testTotalsAreZeroWhenThereIsNothingToPay(): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('searchInstallments')->willReturn([]);

        $payments = $this->searcher($repository)->execute();

        self::assertSame('0.00', $payments->monthTotalAmount);
        self::assertSame('0.00', $payments->overdueTotalAmount);
        self::assertSame('0.00', $payments->processingTotalAmount);
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
