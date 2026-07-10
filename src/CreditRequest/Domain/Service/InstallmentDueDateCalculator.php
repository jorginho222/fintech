<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

final class InstallmentDueDateCalculator
{
    private const int DAYS_BETWEEN_INSTALLMENTS = 30;

    /**
     * @return list<\DateTimeImmutable>
     */
    public function calculate(\DateTimeImmutable $from, int $installmentQuantity): array
    {
        $dueDates    = [];
        $previousDate = $from;

        for ($i = 0; $i < $installmentQuantity; $i++) {
            $dueDate = $this->nextBusinessDay(
                $previousDate->modify(sprintf('+%d days', self::DAYS_BETWEEN_INSTALLMENTS))
            );
            $dueDates[]  = $dueDate;
            $previousDate = $dueDate;
        }

        return $dueDates;
    }

    private function nextBusinessDay(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return match ((int) $date->format('N')) {
            6 => $date->modify('+2 days'),
            7 => $date->modify('+1 day'),
            default => $date,
        };
    }
}
