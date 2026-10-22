<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestExpiredProposalDeleter;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestExpiredProposalDeleterTest extends TestCase
{
    public function testDeletesEachExpiredProposalPastItsRetentionPeriod(): void
    {
        $firstCreditRequest = $this->createCreditRequest('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb');
        $secondCreditRequest = $this->createCreditRequest('dddddddd-dddd-4ddd-dddd-dddddddddddd');
        $now = new \DateTimeImmutable('2026-07-18');

        $creditRequestRepository = $this->createMock(CreditRequestRepositoryInterface::class);
        $creditRequestRepository->expects(self::once())
            ->method('findExpiredProposalsForDeletion')
            ->with(new \DateTimeImmutable('2026-07-03'))
            ->willReturn([$firstCreditRequest, $secondCreditRequest]);
        $creditRequestRepository->expects(self::exactly(2))
            ->method('delete')
            ->with(self::logicalOr($firstCreditRequest, $secondCreditRequest));

        $deletedCount = (new CreditRequestExpiredProposalDeleter($creditRequestRepository))->execute($now);

        self::assertSame(2, $deletedCount);
    }

    private function createCreditRequest(string $id): CreditRequest
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );

        return new CreditRequest(
            $id,
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::ProposalExpired,
        );
    }
}
