<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Exception\CreditRequestApplicationReferenceAlreadyExistsException;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreditRequestApplicationCreator
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
    ) {}

    public function execute(Company $company, string $reference, int $amount, int $installmentQuantity): CreditRequestApplication
    {
        if ($this->creditRequestApplicationRepository->findByCompanyAndReference($company->getId(), $reference) !== null) {
            throw new CreditRequestApplicationReferenceAlreadyExistsException();
        }

        $creditRequestApplication = new CreditRequestApplication(
            Uuid::v4()->toRfc4122(),
            $reference,
            (string) $amount,
            $installmentQuantity,
            $company,
        );

        $this->creditRequestApplicationRepository->save($creditRequestApplication);

        return $creditRequestApplication;
    }
}
