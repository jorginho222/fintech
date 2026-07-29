<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

use App\Company\Domain\Model\Company;

class CreditRequestApplication
{
    private CreditRequestApplicationStatus $status;
    private ?CreditRequest $creditRequest;

    public function __construct(
        private string $id,
        private string $amount,
        private int    $installmentQuantity,
        private Company $company,
    )
    {
        $this->status = CreditRequestApplicationStatus::EvaluationPending;
        $this->creditRequest = null;
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
}
