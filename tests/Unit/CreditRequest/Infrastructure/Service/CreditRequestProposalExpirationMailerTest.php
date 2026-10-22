<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Infrastructure\Service;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Infrastructure\Service\CreditRequestProposalExpirationMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class CreditRequestProposalExpirationMailerTest extends TestCase
{
    public function testSendsAnExpirationEmailToTheCompany(): void
    {
        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20123456780',
            'empresa@example.com',
            TaxStatus::Monotributo,
        );

        $creditRequest = new CreditRequest(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '10000000',
            '60.00',
            12,
            $company,
            CreditRequestStatus::Proposal,
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($creditRequest): bool {
                self::assertSame(['empresa@example.com'], array_map(
                    static fn ($address) => $address->getAddress(),
                    $email->getTo(),
                ));
                self::assertSame(['no-reply@fintech.local'], array_map(
                    static fn ($address) => $address->getAddress(),
                    $email->getFrom(),
                ));
                self::assertSame('Your credit request proposal has expired', $email->getSubject());
                self::assertStringContainsString('Empresa SRL', (string) $email->getTextBody());
                self::assertStringContainsString(
                    $creditRequest->getApprovalLimitDate()->format('Y-m-d H:i'),
                    (string) $email->getTextBody(),
                );

                return true;
            }));

        (new CreditRequestProposalExpirationMailer($mailer, 'no-reply@fintech.local'))->notify($creditRequest);
    }
}
