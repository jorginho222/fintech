<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Shared\Application\UseCase\EmailSender;

final class InstallmentPaymentMailer implements InstallmentPaymentNotifierInterface
{
    public function __construct(
        private readonly EmailSender $emailSender,
        private readonly string $fromAddress,
    ) {
    }

    public function notifyPaymentProcessing(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'Pago en proceso',
            'Recibimos su solicitud de pago y la estamos procesando. Le avisaremos cuando tengamos el resultado.',
        );
    }

    public function notifyPaymentApproved(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'Pago aprobado',
            'Su pago fue aprobado y la cuota quedó pagada.',
        );
    }

    public function notifyPaymentRejected(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'Pago rechazado',
            'Su pago fue rechazado. Puede volver a intentarlo.',
        );
    }

    public function notifyPaymentProcessingFailed(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'No se pudo procesar el pago',
            'No pudimos procesar su pago. Puede volver a intentarlo.',
        );
    }

    public function notifyPaymentProcessingTimeout(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'No se pudo confirmar el pago',
            'No recibimos la confirmación de su pago dentro de las 72 horas hábiles. Puede volver a intentarlo; la cuota no se le cobrará dos veces.',
        );
    }

    public function notifyInstallmentOverdue(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'Cuota vencida',
            'La siguiente cuota está vencida y genera intereses por mora.',
        );
    }

    public function notifyDueDateReminder(Installment $installment): void
    {
        $this->sendForInstallment(
            $installment,
            'Recordatorio de vencimiento',
            'Le recordamos que la siguiente cuota está próxima a vencer.',
        );
    }

    public function notifyCreditRequestPaid(CreditRequest $creditRequest): void
    {
        $this->send(
            $creditRequest,
            'Crédito pagado en su totalidad',
            sprintf('Su crédito "%s" fue pagado en su totalidad.', $creditRequest->getReference()),
        );
    }

    private function sendForInstallment(Installment $installment, string $subject, string $message): void
    {
        $creditRequest = $installment->getCreditRequest();

        $this->send($creditRequest, $subject, sprintf(
            "%s\n\nCrédito: %s\nCuota: %d/%d\nImporte: $%s\nVencimiento: %s",
            $message,
            $creditRequest->getReference(),
            $installment->getPeriodNumber(),
            $creditRequest->getInstallmentQuantity(),
            bcadd($installment->getTotalAmount(), '0', 2),
            $installment->getDueDate()?->format('d/m/Y') ?? '-',
        ));
    }

    private function send(CreditRequest $creditRequest, string $subject, string $body): void
    {
        $company = $creditRequest->getCompany();

        $this->emailSender->execute(
            $this->fromAddress,
            $company->getEmail(),
            $subject,
            sprintf("Hola %s,\n\n%s\n\nSaludos,\nFintech", $company->getSocialReason(), $body),
        );
    }
}
