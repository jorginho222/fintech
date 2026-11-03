<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Infrastructure\Service\InstallmentPaymentMailer;
use App\Shared\Application\UseCase\EmailSender;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class InstallmentPaymentMailerTest extends TestCase
{
    use InstallmentFixture;

    public function testInstallmentEmailIdentifiesTheInstallment(): void
    {
        $email = $this->sentEmail(fn (InstallmentPaymentMailer $mailer) => $mailer->notifyPaymentApproved($this->installment()));

        self::assertSame('no-reply@fintech.local', $email->getFrom()[0]->getAddress());
        self::assertSame('empresa@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Pago aprobado', $email->getSubject());

        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Empresa SRL', $text);
        self::assertStringContainsString('Crédito 1', $text);
        self::assertStringContainsString('3/12', $text);
        self::assertStringContainsString('$893833.33', $text);
        self::assertStringContainsString('15/11/2026', $text);
    }

    public function testProcessingFailedEmailDoesNotIncludeTheServiceErrorMessage(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt(new \DateTimeImmutable());
        $installment->failPaymentAttempt(new \DateTimeImmutable(), 'Gateway error 500');

        $email = $this->sentEmail(fn (InstallmentPaymentMailer $mailer) => $mailer->notifyPaymentProcessingFailed($installment));

        self::assertSame('No se pudo procesar el pago', $email->getSubject());
        self::assertStringNotContainsString('Gateway error', (string) $email->getTextBody());
    }

    public function testCreditRequestPaidEmailNamesTheCreditRequest(): void
    {
        $creditRequest = $this->creditRequest();

        $email = $this->sentEmail(fn (InstallmentPaymentMailer $mailer) => $mailer->notifyCreditRequestPaid($creditRequest));

        self::assertSame('Crédito pagado en su totalidad', $email->getSubject());
        self::assertStringContainsString('Crédito 1', (string) $email->getTextBody());
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

        $notify(new InstallmentPaymentMailer(new EmailSender($symfonyMailer), 'no-reply@fintech.local'));

        return $sent;
    }
}
