<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestProposalExpirer;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\CreditRequestProposalExpirationNotifierInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestProposalExpirerTest extends TestCase
{
    public function testExpiresEachProposalPastItsApprovalLimitDateAndNotifiesTheCompany(): void
    {
        $firstCreditRequest = $this->createCreditRequest('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb');
        $secondCreditRequest = $this->createCreditRequest('dddddddd-dddd-4ddd-dddd-dddddddddddd');
        $now = new \DateTimeImmutable('2026-07-21');

        $creditRequestRepository = $this->createMock(CreditRequestRepositoryInterface::class);
        $creditRequestRepository->expects(self::once())
            ->method('findExpiredProposals')
            ->with($now)
            ->willReturn([$firstCreditRequest, $secondCreditRequest]);
        $creditRequestRepository->expects(self::exactly(2))
            ->method('save')
            ->with(self::logicalOr($firstCreditRequest, $secondCreditRequest));

        $notifier = $this->createMock(CreditRequestProposalExpirationNotifierInterface::class);
        $notifier->expects(self::exactly(2))
            ->method('notify')
            ->with(self::logicalOr($firstCreditRequest, $secondCreditRequest));

        $expiredCount = (new CreditRequestProposalExpirer($creditRequestRepository, $notifier))->execute($now);

        self::assertSame(2, $expiredCount);
        self::assertSame(CreditRequestStatus::ProposalExpired, $firstCreditRequest->getStatus());
        self::assertSame(CreditRequestStatus::ProposalExpired, $secondCreditRequest->getStatus());
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
            CreditRequestStatus::Proposal,
        );
    }
}
