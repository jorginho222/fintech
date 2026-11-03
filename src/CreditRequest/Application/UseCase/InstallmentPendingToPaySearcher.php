<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPendingToPaySearchDto;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class InstallmentPendingToPaySearcher
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    /**
     * @return Installment[]
     */
    public function execute(InstallmentPendingToPaySearchDto $dto): array
    {
        $periodStart = new \DateTimeImmutable(sprintf('%04d-%02d-01', $dto->year, $dto->month));
        $periodEnd   = $periodStart->modify('first day of next month');

        $installments = $this->creditRequestRepository->findPendingInstallmentsToPay(
            $this->authenticatedCompanyIdProvider->getCompanyId(),
            $periodStart,
            $periodEnd,
        );

        return array_values(array_filter(
            $installments,
            static fn (Installment $installment): bool => !$installment->isPaymentProcessing(),
        ));
    }
}
