<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Model\Installment;

final class InstallmentPenaltyInterestUpdater
{
    // TODO: derive late payment rate from credit product configuration instead of hardcoding
    private const string LATE_PAYMENT_INTEREST_RATE = '0.90'; // 90% TNA de mora (simulada)
    private const string PENALTY_IVA_RATE = '0.21';
    private const int    CALC_SCALE = 10;

    /**
     * Accrues late-payment interest on the outstanding capital for every day since the last calculation,
     * or since the due date if it was never calculated, so a day is not lost if the scheduled command doesn't run.
     * A no-op if already applied for the given day, to survive re-runs of the scheduled command.
     *
     * @return bool whether new days of penalty interest were accrued
     */
    public function execute(Installment $installment, \DateTimeImmutable $today): bool
    {
        $daysToAccrue = $this->daysToAccrue($installment, $today);
        if ($daysToAccrue === 0) {
            return false;
        }

        $dailyRate = bcdiv(self::LATE_PAYMENT_INTEREST_RATE, '365', self::CALC_SCALE);

        $dailyPenalty = $this->bcRound(bcmul($installment->getCapitalAmount(), $dailyRate, self::CALC_SCALE));

        $newPenaltyTotal = bcadd($installment->getPenaltyInterestAmount(), bcmul($dailyPenalty, (string) $daysToAccrue, 2), 2);
        $installment->setPenaltyInterestAmount($newPenaltyTotal);

        $dailyPenaltyIva21Tax = $this->bcRound(bcmul($dailyPenalty, self::PENALTY_IVA_RATE, self::CALC_SCALE));
        $newPenaltyIva21Tax = bcadd($installment->getPenaltyIva21Tax(), bcmul($dailyPenaltyIva21Tax, (string) $daysToAccrue, 2), 2);
        $installment->setPenaltyIva21Tax($newPenaltyIva21Tax);

        $newTotal = bcadd(
            bcadd(
                bcadd(
                    bcadd($installment->getCapitalAmount(), $installment->getInterestAmount(), 2),
                    $installment->getTaxOnInterestAmount(),
                    2,
                ),
                $newPenaltyTotal,
                2,
            ),
            $newPenaltyIva21Tax,
            2,
        );
        $installment->setTotalAmount($newTotal);

        $installment->setLastPenaltyCalculationAt($today);

        return true;
    }

    private function daysToAccrue(Installment $installment, \DateTimeImmutable $today): int
    {
        $accruedUntil = $installment->getLastPenaltyCalculationAt() ?? $installment->getDueDate();
        if ($accruedUntil === null) {
            return 1;
        }

        $elapsed = $accruedUntil->setTime(0, 0)->diff($today->setTime(0, 0));

        return $elapsed->invert === 1 ? 0 : $elapsed->days;
    }

    private function bcRound(string $value, int $scale = 2): string
    {
        $sign = str_starts_with($value, '-') ? '-' : '';

        return bcadd($value, $sign . '0.' . str_repeat('0', $scale) . '5', $scale);
    }
}
