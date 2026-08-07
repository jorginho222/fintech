<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

use App\Company\Domain\Model\Company;

class CreditRequestApplication
{
    private CreditRequestApplicationStatus $status;
    private ?CreditRequest $creditRequest;
    private ?string $rejectionReason;

    public function __construct(
        private string  $id,
        private string  $amount,
        private int     $installmentQuantity,
        private Company $company,
    )
    {
        $this->status = CreditRequestApplicationStatus::EvaluationPending;
        $this->creditRequest = null;
        $this->rejectionReason = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getInstallmentQuantity(): int
    {
        return $this->installmentQuantity;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getStatus(): CreditRequestApplicationStatus
    {
        return $this->status;
    }

    public function getCreditRequest(): ?CreditRequest
    {
        return $this->creditRequest;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function approve(): void
    {
        $this->status = CreditRequestApplicationStatus::Approved;
        $this->rejectionReason = null;
    }

    public function reject(string $rejectionReason): void
    {
        $this->status = CreditRequestApplicationStatus::Rejected;
        $this->rejectionReason = $rejectionReason;
    }

    public function setCreditRequest(CreditRequest $creditRequest): void
    {
        $this->creditRequest = $creditRequest;
    }
}
