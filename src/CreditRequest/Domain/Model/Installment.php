<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

use App\CreditRequest\Domain\Exception\InstallmentAlreadyPaidException;

class Installment
{
    private ?\DateTimeImmutable $dueDate;
    private ?\DateTimeImmutable $lastPenaltyCalculationAt;
    private string $penaltyInterestAmount;
    private string $penaltyIva21Tax;

    public function __construct(
        private string            $id,
        private int               $periodNumber,
        private string            $capitalAmount,
        private string            $interestAmount,
        private string            $taxOnInterestAmount,
        private string            $totalAmount,
        private InstallmentStatus $status,
        private CreditRequest     $creditRequest
    )
    {
        $this->dueDate = null;
        $this->lastPenaltyCalculationAt = null;
        $this->penaltyInterestAmount = '0.0000';
        $this->penaltyIva21Tax = '0.0000';
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

    public function getPenaltyInterestAmount(): string
    {
        return $this->penaltyInterestAmount;
    }

    public function getPenaltyIva21Tax(): string
    {
        return $this->penaltyIva21Tax;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getLastPenaltyCalculationAt(): ?\DateTimeImmutable
    {
        return $this->lastPenaltyCalculationAt;
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

    public function pay(): void
    {
        if ($this->status === InstallmentStatus::Paid) {
            throw new InstallmentAlreadyPaidException();
        }

        $this->status = InstallmentStatus::Paid;
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

    public function setPenaltyInterestAmount(string $penaltyInterestAmount): void
    {
        $this->penaltyInterestAmount = $penaltyInterestAmount;
    }

    public function setPenaltyIva21Tax(string $penaltyIva21Tax): void
    {
        $this->penaltyIva21Tax = $penaltyIva21Tax;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function setLastPenaltyCalculationAt(\DateTimeImmutable $lastPenaltyCalculationAt): void
    {
        $this->lastPenaltyCalculationAt = $lastPenaltyCalculationAt;
    }
}
