<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Infrastructure\Service;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Infrastructure\Service\CreditRequestApplicationMailer;
use App\Shared\Application\UseCase\EmailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class CreditRequestApplicationMailerTest extends TestCase
{
    public function testRequestSentEmailIdentifiesTheApplication(): void
    {
        $sent = null;
        $symfonyMailer = $this->createMock(MailerInterface::class);
        $symfonyMailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sent): void {
                $sent = $email;
            });

        $mailer = new CreditRequestApplicationMailer(new EmailSender($symfonyMailer), 'no-reply@fintech.local');
        $mailer->notifyRequestSent(new CreditRequestApplication(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Ampliación planta',
            '1000000',
            12,
            new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@example.com', TaxStatus::Monotributo),
        ));

        self::assertSame('no-reply@fintech.local', $sent->getFrom()[0]->getAddress());
        self::assertSame('empresa@example.com', $sent->getTo()[0]->getAddress());
        self::assertSame('Solicitud de crédito enviada a evaluación', $sent->getSubject());

        $text = (string) $sent->getTextBody();
        self::assertStringContainsString('Empresa SRL', $text);
        self::assertStringContainsString('Ampliación planta', $text);
        self::assertStringContainsString('$1000000.00', $text);
        self::assertStringContainsString('Cuotas: 12', $text);
    }

    public function testApprovedEmailIncludesTheRateAndTheConfirmationLimit(): void
    {
        $company = $this->company();
        $application = $this->application($company);
        $creditRequest = new CreditRequest(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Ampliación planta',
            '1000000',
            '55.00',
            12,
            $company,
            CreditRequestStatus::Proposal,
        );
        $application->approve();
        $application->setCreditRequest($creditRequest);

        $email = $this->sentEmail(static fn (CreditRequestApplicationMailer $mailer) => $mailer->notifyApproved($application));

        self::assertSame('Solicitud de crédito aprobada', $email->getSubject());
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('TNA de 55.00%', $text);
        self::assertStringContainsString($creditRequest->getApprovalLimitDate()->format('d/m/Y H:i'), $text);
        self::assertStringContainsString('Ampliación planta', $text);
    }

    public function testRejectedEmailDoesNotIncludeTheRejectionReasonCode(): void
    {
        $application = $this->application($this->company());
        $application->reject('CAPACIDAD_PAGO_EXCEDIDA');

        $email = $this->sentEmail(static fn (CreditRequestApplicationMailer $mailer) => $mailer->notifyRejected($application));

        self::assertSame('Solicitud de crédito rechazada', $email->getSubject());
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Ampliación planta', $text);
        self::assertStringNotContainsString('CAPACIDAD_PAGO_EXCEDIDA', $text);
    }

    private function company(): Company
    {
        return new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@example.com', TaxStatus::Monotributo);
    }

    private function application(Company $company): CreditRequestApplication
    {
        return new CreditRequestApplication('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', 'Ampliación planta', '1000000', 12, $company);
    }

    private function sentEmail(callable $notify): Email
    {
        $sent = null;
        $symfonyMailer = $this->createMock(MailerInterface::class);
        $symfonyMailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sent): void {
                $sent = $email;
            });

        $notify(new CreditRequestApplicationMailer(new EmailSender($symfonyMailer), 'no-reply@fintech.local'));

        return $sent;
    }
}
