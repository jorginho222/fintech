<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Serializer;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;

final class CreditRequestSerializer
{
    public function serialize(CreditRequest $creditRequest): array
    {
        return [
            'id' => $creditRequest->getId(),
            'status' => $creditRequest->getStatus()->value,
            'approvalLimitDate' => $creditRequest->getApprovalLimitDate()->format('d/m/y'),
            'totalAmount' => $creditRequest->getTotalAmount(),
            'nominalInterestRate' => $creditRequest->getNominalInterestRate(),
            'installmentQuantity' => $creditRequest->getInstallmentQuantity(),
            'createdAt' => $creditRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'company' => [
                'id' => $creditRequest->getCompany()->getId(),
                'socialReason' => $creditRequest->getCompany()->getSocialReason(),
                'cuit' => $creditRequest->getCompany()->getCuit(),
                'email' => $creditRequest->getCompany()->getEmail(),
                'taxStatus' => $creditRequest->getCompany()->getTaxStatus()->value,
            ],
            'installments' => array_map(
                static fn (Installment $installment): array => [
                    'id' => $installment->getId(),
                    'periodNumber' => $installment->getPeriodNumber(),
                    'capitalAmount' => $installment->getCapitalAmount(),
                    'interestAmount' => $installment->getInterestAmount(),
                    'taxOnInterestAmount' => $installment->getTaxOnInterestAmount(),
                    'totalAmount' => $installment->getTotalAmount(),
                    'dueDate' => $installment->getDueDate()?->format(\DateTimeInterface::ATOM),
                    'status' => $installment->getStatus()->value,
                ],
                $creditRequest->getInstallmentCollection()->toArray(),
            ),
        ];
    }
}
