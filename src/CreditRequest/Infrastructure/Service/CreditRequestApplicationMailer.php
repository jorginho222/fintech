<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Service\CreditRequestApplicationNotifierInterface;
use App\Shared\Application\UseCase\EmailSender;

final class CreditRequestApplicationMailer implements CreditRequestApplicationNotifierInterface
{
    public function __construct(
        private readonly EmailSender $emailSender,
        private readonly string $fromAddress,
    ) {
    }

    public function notifyRequestSent(CreditRequestApplication $creditRequestApplication): void
    {
        $this->send(
            $creditRequestApplication,
            'Solicitud de crédito enviada a evaluación',
            'Su solicitud de crédito fue enviada a evaluación. Le avisaremos cuando tengamos el resultado.',
        );
    }

    public function notifyApproved(CreditRequestApplication $creditRequestApplication): void
    {
        $creditRequest = $creditRequestApplication->getCreditRequest();

        $this->send(
            $creditRequestApplication,
            'Solicitud de crédito aprobada',
            sprintf(
                'Su solicitud de crédito fue aprobada, con una TNA de %s%%. Para aceptar la propuesta, debe confirmarla antes del %s (hora UTC).',
                $creditRequest?->getNominalInterestRate(),
                $creditRequest?->getApprovalLimitDate()->format('d/m/Y H:i'),
            ),
        );
    }

    public function notifyRejected(CreditRequestApplication $creditRequestApplication): void
    {
        $this->send(
            $creditRequestApplication,
            'Solicitud de crédito rechazada',
            'Lamentamos informarle que su solicitud de crédito no fue aprobada.',
        );
    }

    private function send(CreditRequestApplication $creditRequestApplication, string $subject, string $message): void
    {
        $company = $creditRequestApplication->getCompany();

        $this->emailSender->execute(
            $this->fromAddress,
            $company->getEmail(),
            $subject,
            sprintf(
                "Hola %s,\n\n%s\n\nReferencia: %s\nImporte: $%s\nCuotas: %d\n\nSaludos,\nFintech",
                $company->getSocialReason(),
                $message,
                $creditRequestApplication->getReference(),
                bcadd($creditRequestApplication->getAmount(), '0', 2),
                $creditRequestApplication->getInstallmentQuantity(),
            ),
        );
    }
}
