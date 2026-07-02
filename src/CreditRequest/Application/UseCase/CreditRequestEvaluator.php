<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Exception\CompanyNotFoundException;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Domain\Exception\ExceededAmountException;
use App\CreditRequest\Domain\Exception\InsufficientScoreException;
use App\CreditRequest\Domain\Model\CreditRequest;

final class CreditRequestEvaluator
{
    private const int MAX_APPROVED_AMOUNT = 50_000_000;

    public function __construct(
        private readonly CompanyRepositoryInterface  $companyRepository,
        private readonly CreditRequestCreator        $creditRequestCreator,
    ) {}

    public function execute(CreditRequestEvaluateDto $dto): CreditRequest
    {
        $company = $this->companyRepository->findById($dto->companyId);
        if ($company === null) {
            throw new CompanyNotFoundException();
        }

        $lastCuitDigit = (int) substr($company->getCuit(), -1);

        if ($lastCuitDigit % 2 !== 0) {
            throw new InsufficientScoreException();
        }

        if ($dto->amount > self::MAX_APPROVED_AMOUNT) {
            throw new ExceededAmountException();
        }

        return $this->creditRequestCreator->execute($company, $dto->amount, $dto->installmentQuantity);
    }
}
