<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Scheduler;

use App\CreditRequest\Application\Message\AccrueInstallmentPenaltyInterestMessage;
use App\CreditRequest\Application\Message\DeleteExpiredCreditRequestProposalsMessage;
use App\CreditRequest\Application\Message\ExpireCreditRequestProposalsMessage;
use App\CreditRequest\Application\Message\MarkOverdueInstallmentsMessage;
use App\CreditRequest\Application\Message\SendInstallmentDueDateRemindersMessage;
use App\CreditRequest\Application\Message\TimeoutInstallmentPaymentAttemptsMessage;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('credit_request')]
final class CreditRequestSchedule implements ScheduleProviderInterface
{
    // Fixed times in UTC, so restarting the worker doesn't move or postpone the runs.
    private const string DAILY_MAINTENANCE_TIME = '03:00';
    private const string DUE_DATE_REMINDER_TIME = '12:00';

    public function __construct(private readonly CacheInterface $cache) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', $this->async(new ExpireCreditRequestProposalsMessage()), from: self::DAILY_MAINTENANCE_TIME))
            ->add(RecurringMessage::every('1 day', $this->async(new MarkOverdueInstallmentsMessage()), from: self::DAILY_MAINTENANCE_TIME))
            ->add(RecurringMessage::every('1 day', $this->async(new AccrueInstallmentPenaltyInterestMessage()), from: self::DAILY_MAINTENANCE_TIME))
            ->add(RecurringMessage::every('1 day', $this->async(new DeleteExpiredCreditRequestProposalsMessage()), from: self::DAILY_MAINTENANCE_TIME))
            ->add(RecurringMessage::every('1 hour', $this->async(new TimeoutInstallmentPaymentAttemptsMessage()), from: '00:00'))
            ->add(RecurringMessage::every('1 day', $this->async(new SendInstallmentDueDateRemindersMessage()), from: self::DUE_DATE_REMINDER_TIME))
            // Remembers the last run, so a run missed while the worker was down happens when it comes back.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }

    /**
     * Runs the task through the async transport, because the scheduler transport doesn't retry a failed task.
     */
    private function async(object $message): RedispatchMessage
    {
        return new RedispatchMessage($message, 'async');
    }
}
