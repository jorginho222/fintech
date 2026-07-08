<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Scheduler;

use App\CreditRequest\Application\Message\ExpireCreditRequestProposalsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('credit_request')]
final class CreditRequestSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', new ExpireCreditRequestProposalsMessage()));
    }
}
