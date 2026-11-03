<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultDto;
use App\CreditRequest\Application\DTO\InstallmentPaymentResultOutcome;
use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class InstallmentPaymentResultHandler
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly CreditRequestPaidChecker         $creditRequestPaidChecker,
    ) {}

    public function execute(InstallmentPaymentResultDto $dto): InstallmentPaymentResultOutcome
    {
        $installment = $this->creditRequestRepository->findInstallmentById($dto->installmentId);
        if ($installment === null) {
            throw new InstallmentNotFoundException();
        }

        $now = new \DateTimeImmutable();

        $applied = match ($dto->status) {
            InstallmentPaymentResultDto::STATUS_APPROVED => $installment->approvePaymentAttempt($now),
            InstallmentPaymentResultDto::STATUS_REJECTED => $installment->rejectPaymentAttempt($now),
            InstallmentPaymentResultDto::STATUS_PROCESSING_FAILED => $installment->failPaymentAttempt($now, (string) $dto->errorMessage),
        };
        if (!$applied) {
            return new InstallmentPaymentResultOutcome($installment, null, false);
        }

        $this->creditRequestRepository->saveInstallment($installment);

        return new InstallmentPaymentResultOutcome(
            $installment,
            $installment->getLastPaymentAttemptStatus(),
            $this->isCreditRequestPaidBy($installment),
        );
    }

    private function isCreditRequestPaidBy(Installment $installment): bool
    {
        return $installment->getLastPaymentAttemptStatus() === InstallmentPaymentAttemptStatus::Approved
            && $this->creditRequestPaidChecker->execute($installment->getCreditRequest());
    }
}
