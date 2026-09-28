<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Scheduler;

use App\Company\Application\Message\DeleteExpiredRevokedTokensMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('company')]
final class CompanySchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', new DeleteExpiredRevokedTokensMessage()));
    }
}
