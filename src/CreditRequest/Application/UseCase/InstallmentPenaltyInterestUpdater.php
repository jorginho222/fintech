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
     * Accrues one day of late-payment interest on the outstanding capital.
     * A no-op if already applied for the given day, to survive re-runs of the scheduled command.
     *
     * @return bool whether a new day of penalty interest was accrued
     */
    public function execute(Installment $installment, \DateTimeImmutable $today): bool
    {
        if ($installment->getLastPenaltyCalculationAt()?->format('Y-m-d') === $today->format('Y-m-d')) {
            return false;
        }

        $dailyRate = bcdiv(self::LATE_PAYMENT_INTEREST_RATE, '365', self::CALC_SCALE);

        $dailyPenalty = $this->bcRound(bcmul($installment->getCapitalAmount(), $dailyRate, self::CALC_SCALE));

        $newPenaltyTotal = bcadd($installment->getPenaltyInterestAmount(), $dailyPenalty, 2);
        $installment->setPenaltyInterestAmount($newPenaltyTotal);

        $dailyPenaltyIva21Tax = $this->bcRound(bcmul($dailyPenalty, self::PENALTY_IVA_RATE, self::CALC_SCALE));
        $newPenaltyIva21Tax = bcadd($installment->getPenaltyIva21Tax(), $dailyPenaltyIva21Tax, 2);
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

    private function bcRound(string $value, int $scale = 2): string
    {
        $sign = str_starts_with($value, '-') ? '-' : '';

        return bcadd($value, $sign . '0.' . str_repeat('0', $scale) . '5', $scale);
    }
}
