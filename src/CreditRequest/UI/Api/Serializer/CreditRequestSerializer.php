<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Serializer;

use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;

final class CreditRequestSerializer
{
    public function serialize(CreditRequest $creditRequest): array
    {
        $installments = $creditRequest->getInstallmentCollection()->toArray();
        usort(
            $installments,
            static fn (Installment $first, Installment $second): int => $first->getPeriodNumber() <=> $second->getPeriodNumber(),
        );
        $upcomingInstallment = $this->findUpcomingInstallment($installments);

        return [
            'id' => $creditRequest->getId(),
            'status' => $creditRequest->getStatus()->value,
            'approvalLimitDate' => $creditRequest->getApprovalLimitDate()->format('d/m/y'),
            'totalAmount' => $creditRequest->getTotalAmount(),
            'nominalInterestRate' => $creditRequest->getNominalInterestRate(),
            'installmentQuantity' => $creditRequest->getInstallmentQuantity(),
            'paidInstallments' => count(array_filter(
                $installments,
                static fn (Installment $installment): bool => $installment->getStatus() === InstallmentStatus::Paid,
            )),
            'proposalDate' => $creditRequest->getProposalDate()->format(\DateTimeInterface::ATOM),
            'activationDate' => $creditRequest->getActivationDate()?->format(\DateTimeInterface::ATOM),
            'company' => [
                'id' => $creditRequest->getCompany()->getId(),
                'socialReason' => $creditRequest->getCompany()->getSocialReason(),
                'cuit' => $creditRequest->getCompany()->getCuit(),
                'email' => $creditRequest->getCompany()->getEmail(),
                'taxStatus' => $creditRequest->getCompany()->getTaxStatus()->value,
            ],
            'upcomingInstallment' => $upcomingInstallment === null
                ? null
                : $this->serializeInstallment($upcomingInstallment),
            'installments' => array_map($this->serializeInstallment(...), $installments),
        ];
    }

    /**
     * @param Installment[] $installments
     */
    private function findUpcomingInstallment(array $installments): ?Installment
    {
        $pendingInstallments = array_filter(
            $installments,
            static fn (Installment $installment): bool => $installment->getStatus() === InstallmentStatus::Pending,
        );

        usort($pendingInstallments, static function (Installment $first, Installment $second): int {
            $firstDueDate = $first->getDueDate();
            $secondDueDate = $second->getDueDate();

            if ($firstDueDate !== null && $secondDueDate !== null) {
                return $firstDueDate <=> $secondDueDate;
            }

            if ($firstDueDate !== null) {
                return -1;
            }

            if ($secondDueDate !== null) {
                return 1;
            }

            return $first->getPeriodNumber() <=> $second->getPeriodNumber();
        });

        return $pendingInstallments[0] ?? null;
    }

    private function serializeInstallment(Installment $installment): array
    {
        return [
            'id' => $installment->getId(),
            'periodNumber' => $installment->getPeriodNumber(),
            'capitalAmount' => $installment->getCapitalAmount(),
            'interestAmount' => $installment->getInterestAmount(),
            'taxOnInterestAmount' => $installment->getTaxOnInterestAmount(),
            'totalAmount' => $installment->getTotalAmount(),
            'dueDate' => $installment->getDueDate()?->format(\DateTimeInterface::ATOM),
            'status' => $installment->getStatus()->value,
        ];
    }
}
