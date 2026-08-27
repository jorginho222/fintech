<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Domain\Model;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Exception\CreditRequestNotActivableException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use PHPUnit\Framework\TestCase;

final class CreditRequestTest extends TestCase
{
    public function testIsExpiredWhenPastApprovalLimitDate(): void
    {
        $creditRequest = $this->createCreditRequest();

        $afterLimit = $creditRequest->getProposalDate()->modify('+4 days');

        self::assertTrue($creditRequest->isExpired($afterLimit));
    }

    public function testIsNotExpiredBeforeApprovalLimitDate(): void
    {
        $creditRequest = $this->createCreditRequest();

        $beforeLimit = $creditRequest->getProposalDate()->modify('+1 day');

        self::assertFalse($creditRequest->isExpired($beforeLimit));
    }

    public function testExpireChangesStatusFromProposalToProposalExpired(): void
    {
        $creditRequest = $this->createCreditRequest();

        $creditRequest->expire();

        self::assertSame(CreditRequestStatus::ProposalExpired, $creditRequest->getStatus());
    }

    public function testActivateChangesStatusFromProposalToActive(): void
    {
        $creditRequest = $this->createCreditRequest();

        $creditRequest->activate(new \DateTimeImmutable());

        self::assertSame(CreditRequestStatus::Active, $creditRequest->getStatus());
    }

    public function testActivateSetsActivationDate(): void
    {
        $creditRequest = $this->createCreditRequest();

        self::assertNull($creditRequest->getActivationDate());

        $now = new \DateTimeImmutable();
        $creditRequest->activate($now);

        self::assertSame($now, $creditRequest->getActivationDate());
    }

    public function testActivateThrowsWhenStatusIsNotProposal(): void
    {
        $creditRequest = $this->createCreditRequest();
        $creditRequest->activate(new \DateTimeImmutable());

        $this->expectException(CreditRequestNotActivableException::class);

        $creditRequest->activate(new \DateTimeImmutable());
    }

    private function createCreditRequest(): CreditRequest
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
            'hashed-password',
        );

        return new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Proposal,
        );
    }
}
