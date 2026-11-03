<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\UseCase;

use App\Shared\Application\UseCase\EmailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EmailSenderTest extends TestCase
{
    public function testSendsAnEmailWithTheGivenValues(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (Email $email): bool {
                self::assertSame('no-reply@fintech.local', $email->getFrom()[0]->getAddress());
                self::assertSame('empresa@example.com', $email->getTo()[0]->getAddress());
                self::assertSame('Asunto', $email->getSubject());
                self::assertSame('Mensaje', $email->getTextBody());

                return true;
            }));

        (new EmailSender($mailer))->execute('no-reply@fintech.local', 'empresa@example.com', 'Asunto', 'Mensaje');
    }
}
