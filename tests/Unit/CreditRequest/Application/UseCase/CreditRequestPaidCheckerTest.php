<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestPaidChecker;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestPaidCheckerTest extends TestCase
{
    public function testMarksCreditRequestAsPaidWhenAllInstallmentsArePaid(): void
    {
        $creditRequest = $this->createCreditRequest();
        $this->addInstallment($creditRequest, 1, InstallmentStatus::Paid);
        $this->addInstallment($creditRequest, 2, InstallmentStatus::Paid);

        $creditRequestRepository = $this->createMock(CreditRequestRepositoryInterface::class);
        $creditRequestRepository->expects(self::once())->method('save')->with($creditRequest);

        (new CreditRequestPaidChecker($creditRequestRepository))->execute($creditRequest);

        self::assertSame(CreditRequestStatus::Paid, $creditRequest->getStatus());
    }

    public function testDoesNotMarkCreditRequestAsPaidWhenSomeInstallmentIsNotPaid(): void
    {
        $creditRequest = $this->createCreditRequest();
        $this->addInstallment($creditRequest, 1, InstallmentStatus::Paid);
        $this->addInstallment($creditRequest, 2, InstallmentStatus::Pending);

        $creditRequestRepository = $this->createMock(CreditRequestRepositoryInterface::class);
        $creditRequestRepository->expects(self::never())->method('save');

        (new CreditRequestPaidChecker($creditRequestRepository))->execute($creditRequest);

        self::assertSame(CreditRequestStatus::Active, $creditRequest->getStatus());
    }

    private function addInstallment(CreditRequest $creditRequest, int $periodNumber, InstallmentStatus $status): void
    {
        $creditRequest->addInstallment(new Installment(
            sprintf('cccccccc-cccc-4ccc-cccc-%012d', $periodNumber),
            $periodNumber,
            '1000000',
            '50.00',
            '10.00',
            '1060.00',
            $status,
            $creditRequest,
        ));
    }

    private function createCreditRequest(): CreditRequest
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );

        return new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Active,
        );
    }
}
