<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

use App\CreditRequest\Domain\Exception\InstallmentAlreadyPaidException;
use App\CreditRequest\Domain\Exception\InstallmentPaymentInProcessException;

class Installment
{
    private ?\DateTimeImmutable $dueDate;
    private ?\DateTimeImmutable $lastPenaltyCalculationAt;
    private string $penaltyInterestAmount;
    private string $penaltyIva21Tax;
    private ?InstallmentPaymentAttemptStatus $lastPaymentAttemptStatus;
    private ?\DateTimeImmutable $lastPaymentAttemptAt;
    private ?string $lastPaymentAttemptErrorMessage;

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
        $this->lastPaymentAttemptStatus = null;
        $this->lastPaymentAttemptAt = null;
        $this->lastPaymentAttemptErrorMessage = null;
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

    public function getLastPaymentAttemptStatus(): ?InstallmentPaymentAttemptStatus
    {
        return $this->lastPaymentAttemptStatus;
    }

    public function getLastPaymentAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastPaymentAttemptAt;
    }

    public function getLastPaymentAttemptErrorMessage(): ?string
    {
        return $this->lastPaymentAttemptErrorMessage;
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

    public function startPaymentAttempt(\DateTimeImmutable $now): void
    {
        if ($this->status === InstallmentStatus::Paid) {
            throw new InstallmentAlreadyPaidException();
        }

        if ($this->isPaymentProcessing()) {
            throw new InstallmentPaymentInProcessException();
        }

        $this->recordPaymentAttempt(InstallmentPaymentAttemptStatus::Processing, $now);
    }

    public function approvePaymentAttempt(\DateTimeImmutable $now): bool
    {
        if (!$this->canReceivePaymentResult()) {
            return false;
        }

        $this->pay();
        $this->recordPaymentAttempt(InstallmentPaymentAttemptStatus::Approved, $now);

        return true;
    }

    public function rejectPaymentAttempt(\DateTimeImmutable $now): bool
    {
        if (!$this->canReceivePaymentResult()) {
            return false;
        }

        $this->recordPaymentAttempt(InstallmentPaymentAttemptStatus::Rejected, $now);

        return true;
    }

    public function failPaymentAttempt(\DateTimeImmutable $now, string $errorMessage): bool
    {
        if (!$this->canReceivePaymentResult()) {
            return false;
        }

        $this->recordPaymentAttempt(InstallmentPaymentAttemptStatus::ProcessingFailed, $now, $errorMessage);

        return true;
    }

    public function timeoutPaymentAttempt(\DateTimeImmutable $now): void
    {
        if (!$this->isPaymentProcessing()) {
            return;
        }

        $this->recordPaymentAttempt(InstallmentPaymentAttemptStatus::ProcessingTimeout, $now);
    }

    public function isPaymentProcessing(): bool
    {
        return $this->lastPaymentAttemptStatus === InstallmentPaymentAttemptStatus::Processing;
    }

    public function markOverdue(): void
    {
        if ($this->status !== InstallmentStatus::Pending || $this->isPaymentProcessing()) {
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

    /**
     * A result that arrives after the timeout still counts: the service may have processed the payment late.
     * A paid installment has an approved attempt and a never attempted one has none, so both are left out.
     */
    private function canReceivePaymentResult(): bool
    {
        return $this->isPaymentProcessing()
            || $this->lastPaymentAttemptStatus === InstallmentPaymentAttemptStatus::ProcessingTimeout;
    }

    private function recordPaymentAttempt(
        InstallmentPaymentAttemptStatus $status,
        \DateTimeImmutable $at,
        ?string $errorMessage = null,
    ): void {
        $this->lastPaymentAttemptStatus = $status;
        $this->lastPaymentAttemptAt = $at;
        $this->lastPaymentAttemptErrorMessage = $errorMessage;
    }
}
