<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

class Installment
{
    private ?\DateTimeImmutable $dueDate;

    public function __construct(
        private string $id,
        private int $periodNumber,
        private string $capitalAmount,
        private string $interestAmount,
        private string $taxOnInterestAmount,
        private string $totalAmount,
        private InstallmentStatus $status,
        private CreditRequest $creditRequest,
    ) {
        $this->dueDate = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPeriodNumber(): int
    {
        return $this->periodNumber;
    }

    public function getCapitalAmount(): string
    {
        return $this->capitalAmount;
    }

    public function getInterestAmount(): string
    {
        return $this->interestAmount;
    }

    public function getTaxOnInterestAmount(): string
    {
        return $this->taxOnInterestAmount;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getStatus(): InstallmentStatus
    {
        return $this->status;
    }

    public function getCreditRequest(): CreditRequest
    {
        return $this->creditRequest;
    }

    public function changeStatus(InstallmentStatus $status): void
    {
        $this->status = $status;
    }

    public function markOverdue(): void
    {
        if ($this->status !== InstallmentStatus::Pending) {
            return;
        }

        $this->status = InstallmentStatus::Overdue;
    }

    public function changeDueDate(\DateTimeImmutable $dueDate): void
    {
        $this->dueDate = $dueDate;
    }
}
