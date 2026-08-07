<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

final class InstallmentCalculator
{
    private const string IVA_RATE    = '0.21';
    private const int    CALC_SCALE  = 10;

    /**
     * @return list<InstallmentCreateDto>
     */
    public function calculate(string $requestedAmount, int $termMonths, string $annualRate): array
    {
        $monthlyRate = bcdiv($annualRate, '12', self::CALC_SCALE);

        // CuotaPura = (requestedAmount × i) / (1 − (1 + i)^(−termMonths))
        $onePlusRate    = bcadd('1', $monthlyRate, self::CALC_SCALE);
        $discountFactor = bcpow($onePlusRate, (string) -$termMonths, self::CALC_SCALE);
        $denominator    = bcsub('1', $discountFactor, self::CALC_SCALE);
        $purePayment    = bcdiv(
            bcmul($requestedAmount, $monthlyRate, self::CALC_SCALE),
            $denominator,
            self::CALC_SCALE
        );

        $debtBalance  = $requestedAmount;
        $installments = [];

        for ($t = 1; $t <= $termMonths; $t++) {
            // Step A: period interest
            $interestAmount = $this->bcRound(bcmul($debtBalance, $monthlyRate, self::CALC_SCALE));

            // Step B: VAT on interest
            $taxOnInterestAmount = $this->bcRound(bcmul($interestAmount, self::IVA_RATE, self::CALC_SCALE));

            // Step C: capital amortization
            if ($t < $termMonths) {
                $capitalAmount = $this->bcRound(bcsub($purePayment, $interestAmount, self::CALC_SCALE));
            } else {
                // Last installment absorbs any rounding residuals
                $capitalAmount = $this->bcRound($debtBalance);
            }

            // Step D: total installment amount (rounded components, no extra rounding needed)
            $totalAmount = bcadd(bcadd($capitalAmount, $interestAmount, 2), $taxOnInterestAmount, 2);

            // Step E: update outstanding balance
            $debtBalance = bcsub($debtBalance, $capitalAmount, self::CALC_SCALE);

            $installments[] = new InstallmentCreateDto(
                $t,
                $capitalAmount,
                $interestAmount,
                $taxOnInterestAmount,
                $totalAmount,
            );
        }

        return $installments;
    }

    private function bcRound(string $value, int $scale = 2): string
    {
        $sign = str_starts_with($value, '-') ? '-' : '';

        return bcadd($value, $sign . '0.' . str_repeat('0', $scale) . '5', $scale);
    }
}
