<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreditRequestApplicationCreator
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
    ) {}

    public function execute(Company $company, int $amount, int $installmentQuantity): CreditRequestApplication
    {
        $creditRequestApplication = new CreditRequestApplication(
            Uuid::v4()->toRfc4122(),
            (string) $amount,
            $installmentQuantity,
            $company,
        );

        $this->creditRequestApplicationRepository->save($creditRequestApplication);

        return $creditRequestApplication;
    }
}
