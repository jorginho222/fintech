<?php

declare(strict_types=1);

namespace App\CreditRequest\Domain\Service;

final class BusinessHoursCalculator
{
    /**
     * Adds hours counting only time on Monday to Friday (all 24 hours). Public holidays are not considered.
     */
    public function addBusinessHours(\DateTimeImmutable $from, int $hours): \DateTimeImmutable
    {
        $remainingSeconds = $hours * 3600;
        $cursor = $this->isWeekend($from) ? $from->modify('next monday') : $from;

        while (true) {
            $nextDay = $cursor->modify('tomorrow');
            $secondsLeftInDay = $nextDay->getTimestamp() - $cursor->getTimestamp();

            if ($remainingSeconds <= $secondsLeftInDay) {
                return $cursor->modify(sprintf('+%d seconds', $remainingSeconds));
            }

            $remainingSeconds -= $secondsLeftInDay;
            $cursor = $this->isWeekend($nextDay) ? $nextDay->modify('next monday') : $nextDay;
        }
    }

    private function isWeekend(\DateTimeImmutable $date): bool
    {
        return (int) $date->format('N') >= 6;
    }
}
