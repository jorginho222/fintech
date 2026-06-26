<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Domain\Exception\ExceededAmountException;
use App\CreditRequest\Domain\Exception\InsufficientScoreException;

final class CreditRequestEvaluator
{
    private const int MAX_APPROVED_AMOUNT = 50_000_000;

    public function __construct(private readonly CompanyRepositoryInterface $companyRepository) {}

    public function execute(CreditRequestEvaluateDto $dto): void
    {
        $company = $this->companyRepository->findById($dto->companyId);
        if ($company === null) {
            throw new \DomainException("Company not found.");
        }

        $lastCuitDigit = (int) substr($company->getCuit(), -1);

        if ($lastCuitDigit % 2 !== 0) {
            throw new InsufficientScoreException();
        }

        if ($dto->amount > self::MAX_APPROVED_AMOUNT) {
            throw new ExceededAmountException();
        }

        // TODO: create credit request in Draft status
    }
}
