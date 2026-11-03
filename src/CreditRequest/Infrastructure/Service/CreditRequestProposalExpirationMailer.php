<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Service\CreditRequestProposalExpirationNotifierInterface;
use App\Shared\Application\UseCase\EmailSender;

final class CreditRequestProposalExpirationMailer implements CreditRequestProposalExpirationNotifierInterface
{
    public function __construct(
        private readonly EmailSender $emailSender,
        private readonly string $fromAddress,
    ) {
    }

    public function notify(CreditRequest $creditRequest): void
    {
        $company = $creditRequest->getCompany();

        $this->emailSender->execute(
            $this->fromAddress,
            $company->getEmail(),
            sprintf('Su propuesta de crédito "%s" venció', $creditRequest->getReference()),
            sprintf(
                "Hola %s,\n\nSu propuesta de crédito \"%s\" venció porque no fue confirmada antes del %s (hora UTC).\n\nSaludos,\nFintech",
                $company->getSocialReason(),
                $creditRequest->getReference(),
                $creditRequest->getApprovalLimitDate()->format('d/m/Y H:i'),
            ),
        );
    }
}
