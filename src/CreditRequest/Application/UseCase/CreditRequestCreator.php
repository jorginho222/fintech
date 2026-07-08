<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentCalculator;
use Symfony\Component\Uid\Uuid;

final class CreditRequestCreator
{
    private const string NOMINAL_INTEREST_RATE = '60.00';

    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly InstallmentCalculator            $installmentCalculator,
    ) {}

    public function execute(Company $company, int $amount, int $installmentQuantity): CreditRequest
    {
        $creditRequest = new CreditRequest(
            Uuid::v4()->toRfc4122(),
            (string) $amount,
            self::NOMINAL_INTEREST_RATE,
            $installmentQuantity,
            $company,
            CreditRequestStatus::Proposal,
        );

        $installmentDtos = $this->installmentCalculator->calculate(
            (string) $amount,
            $installmentQuantity,
        );

        foreach ($installmentDtos as $installmentDto) {
            $creditRequest->addInstallment(new Installment(
                Uuid::v4()->toRfc4122(),
                $installmentDto->periodNumber,
                $installmentDto->capitalAmount,
                $installmentDto->interestAmount,
                $installmentDto->taxOnInterestAmount,
                $installmentDto->totalAmount,
                InstallmentStatus::Pending,
                $creditRequest,
            ));
        }

        $this->creditRequestRepository->save($creditRequest);

        return $creditRequest;
    }
}
