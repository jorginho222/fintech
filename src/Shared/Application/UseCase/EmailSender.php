<?php

declare(strict_types=1);

namespace App\Shared\Application\UseCase;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EmailSender
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function execute(string $from, string $to, string $subject, string $text): void
    {
        $this->mailer->send(
            (new Email())
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text($text),
        );
    }
}
