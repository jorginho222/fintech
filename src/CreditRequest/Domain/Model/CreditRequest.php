<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Model;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Exception\CreditRequestNotActivableException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class CreditRequest
{
    private const int APPROVAL_LIMIT_DAYS = 3;

    private Collection $installmentCollection;
    private \DateTimeImmutable $proposalDate;
    private ?\DateTimeImmutable $activationDate = null;
    private \DateTimeImmutable $approvalLimitDate;

    public function __construct(
        private string              $id,
        private string              $totalAmount,
        private string              $nominalInterestRate,
        private int                 $installmentQuantity,
        private Company             $company,
        private CreditRequestStatus $status,
    )
    {
        $this->installmentCollection = new ArrayCollection();
        $this->proposalDate = new \DateTimeImmutable();
        $this->approvalLimitDate = $this->proposalDate->modify(sprintf('+%d days', self::APPROVAL_LIMIT_DAYS));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function getNominalInterestRate(): string
    {
        return $this->nominalInterestRate;
    }

    public function getInstallmentQuantity(): int
    {
        return $this->installmentQuantity;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getStatus(): CreditRequestStatus
    {
        return $this->status;
    }

    public function getProposalDate(): \DateTimeImmutable
    {
        return $this->proposalDate;
    }

    public function getActivationDate(): ?\DateTimeImmutable
    {
        return $this->activationDate;
    }

    public function getApprovalLimitDate(): \DateTimeImmutable
    {
        return $this->approvalLimitDate;
    }

    public function getInstallmentCollection(): Collection
    {
        return $this->installmentCollection;
    }

    public function changeStatus(CreditRequestStatus $status): void
    {
        $this->status = $status;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->status === CreditRequestStatus::Proposal && $now > $this->approvalLimitDate;
    }

    public function expire(): void
    {
        if ($this->status !== CreditRequestStatus::Proposal) {
            return;
        }

        $this->status = CreditRequestStatus::ProposalExpired;
    }

    public function activate(\DateTimeImmutable $now): void
    {
        if ($this->status !== CreditRequestStatus::Proposal) {
            throw new CreditRequestNotActivableException();
        }

        $this->status = CreditRequestStatus::Active;
        $this->activationDate = $now;
    }

    public function addInstallment(Installment $installment): void
    {
        if (!$this->installmentCollection->contains($installment)) {
            $this->installmentCollection->add($installment);
        }
    }
}
