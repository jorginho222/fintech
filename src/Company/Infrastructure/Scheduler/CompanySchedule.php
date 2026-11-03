<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Scheduler;

use App\Company\Application\Message\DeleteExpiredRevokedTokensMessage;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('company')]
final class CompanySchedule implements ScheduleProviderInterface
{
    // Fixed time in UTC, so restarting the worker doesn't move or postpone the run.
    private const string DAILY_MAINTENANCE_TIME = '03:00';

    public function __construct(private readonly CacheInterface $cache) {}

    public function getSchedule(): Schedule
    {
        // Runs through the async transport, because the scheduler transport doesn't retry a failed task.
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', new RedispatchMessage(new DeleteExpiredRevokedTokensMessage(), 'async'), from: self::DAILY_MAINTENANCE_TIME))
            // Remembers the last run, so a run missed while the worker was down happens when it comes back.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
