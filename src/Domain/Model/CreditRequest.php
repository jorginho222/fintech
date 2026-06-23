<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class CreditRequest
{
    private Collection $installmentCollection;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        private string              $id,
        private string              $totalAmount,
        private string              $nominalInterestRate,
        private Company             $company,
        private CreditRequestStatus $status,
    )
    {
        $this->installmentCollection = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getStatus(): CreditRequestStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getInstallmentCollection(): Collection
    {
        return $this->installmentCollection;
    }

    public function changeStatus(CreditRequestStatus $status): void
    {
        $this->status = $status;
    }

    public function addInstallment(Installment $installment): void
    {
        if (!$this->installmentCollection->contains($installment)) {
            $this->installmentCollection->add($installment);
        }
    }
}
