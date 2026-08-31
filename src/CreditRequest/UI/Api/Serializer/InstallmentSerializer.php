<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Serializer;

use App\CreditRequest\Domain\Model\Installment;

final class InstallmentSerializer
{
    public function serialize(Installment $installment): array
    {
        return [
            'id' => $installment->getId(),
            'periodNumber' => $installment->getPeriodNumber(),
            'capitalAmount' => $installment->getCapitalAmount(),
            'interestAmount' => $installment->getInterestAmount(),
            'taxOnInterestAmount' => $installment->getTaxOnInterestAmount(),
            'totalAmount' => $installment->getTotalAmount(),
            'penaltyInterestAmount' => $installment->getPenaltyInterestAmount(),
            'penaltyIva21Tax' => $installment->getPenaltyIva21Tax(),
            'dueDate' => $installment->getDueDate()?->format(\DateTimeInterface::ATOM),
            'status' => $installment->getStatus()->value,
            'creditRequestId' => $installment->getCreditRequest()->getId(),
        ];
    }
}
