<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Company
{
    private Collection $creditRequestCollection;

    public function __construct(
        private string $id,
        private string $socialReason,
        private string $cuit,
        private string $email,
        private TaxStatus $taxStatus,
    ) {
        if (!preg_match('/^\d{11}$/', $cuit)) {
            throw new \InvalidArgumentException('CUIT must be exactly 11 digits.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        $this->creditRequestCollection = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSocialReason(): string
    {
        return $this->socialReason;
    }

    public function getCuit(): string
    {
        return $this->cuit;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTaxStatus(): TaxStatus
    {
        return $this->taxStatus;
    }

    public function changeSocialReason(string $socialReason): void
    {
        $this->socialReason = $socialReason;
    }

    public function changeEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        $this->email = $email;
    }

    public function changeTaxStatus(TaxStatus $taxStatus): void
    {
        $this->taxStatus = $taxStatus;
    }

    public function getCreditRequestCollection(): Collection
    {
        return $this->creditRequestCollection;
    }

    public function addCreditRequest(CreditRequest $creditRequest): void
    {
        if (!$this->creditRequestCollection->contains($creditRequest)) {
            $this->creditRequestCollection->add($creditRequest);
        }
    }
}
