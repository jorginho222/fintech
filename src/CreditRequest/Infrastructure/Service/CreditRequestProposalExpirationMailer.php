<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Service\CreditRequestProposalExpirationNotifierInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class CreditRequestProposalExpirationMailer implements CreditRequestProposalExpirationNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress,
    ) {
    }

    public function notify(CreditRequest $creditRequest): void
    {
        $company = $creditRequest->getCompany();

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($company->getEmail())
            ->subject('Your credit request proposal has expired')
            ->text(sprintf(
                "Hello %s,\n\nYour credit request proposal has expired because it was not approved before %s.\n\nRegards,\nFintech",
                $company->getSocialReason(),
                $creditRequest->getApprovalLimitDate()->format('Y-m-d H:i'),
            ));

        $this->mailer->send($email);
    }
}
