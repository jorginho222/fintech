<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;

trait InstallmentFixture
{
    private function creditRequest(): CreditRequest
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );

        return new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Crédito 1',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Active,
        );
    }

    private function installment(
        InstallmentStatus $status = InstallmentStatus::Pending,
        ?CreditRequest $creditRequest = null,
        int $periodNumber = 3,
    ): Installment {
        $creditRequest ??= $this->creditRequest();

        $installment = new Installment(
            sprintf('cccccccc-cccc-4ccc-8ccc-%012d', $periodNumber),
            $periodNumber,
            '833333.33',
            '50000.00',
            '10500.00',
            '893833.3300',
            $status,
            $creditRequest,
        );
        $installment->changeDueDate(new \DateTimeImmutable('2026-11-15'));
        $creditRequest->addInstallment($installment);

        return $installment;
    }
}
