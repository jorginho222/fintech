<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Repository\InstallmentSearchCriteria;
use App\CreditRequest\Domain\Service\BusinessHoursCalculator;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;

final class InstallmentPaymentAttemptTimeoutMarker
{
    private const int TIMEOUT_BUSINESS_HOURS = 72;

    public function __construct(
        private readonly CreditRequestRepositoryInterface    $creditRequestRepository,
        private readonly BusinessHoursCalculator             $businessHoursCalculator,
        private readonly InstallmentPaymentNotifierInterface $installmentPaymentNotifier,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $processingInstallments = $this->creditRequestRepository->searchInstallments(
            new InstallmentSearchCriteria(paymentAttemptStatus: InstallmentPaymentAttemptStatus::Processing),
        );

        $timedOutCount = 0;
        foreach ($processingInstallments as $installment) {
            $limit = $this->businessHoursCalculator->addBusinessHours($installment->getLastPaymentAttemptAt(), self::TIMEOUT_BUSINESS_HOURS);
            if ($limit > $now) {
                continue;
            }

            $installment->timeoutPaymentAttempt($now);
            $this->creditRequestRepository->saveInstallment($installment);
            $this->installmentPaymentNotifier->notifyPaymentProcessingTimeout($installment);
            $timedOutCount++;
        }

        return $timedOutCount;
    }
}
