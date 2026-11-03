<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Infrastructure\Scheduler;

use App\CreditRequest\Application\Message\MarkOverdueInstallmentsMessage;
use App\CreditRequest\Application\Message\SendInstallmentDueDateRemindersMessage;
use App\CreditRequest\Application\Message\TimeoutInstallmentPaymentAttemptsMessage;
use App\CreditRequest\Infrastructure\Scheduler\CreditRequestSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

final class CreditRequestScheduleTest extends TestCase
{
    public function testEveryTaskRunsThroughTheAsyncTransport(): void
    {
        $messages = [];
        $after = new \DateTimeImmutable('tomorrow 01:17');
        foreach ((new CreditRequestSchedule(new ArrayAdapter()))->getSchedule()->getRecurringMessages() as $recurringMessage) {
            foreach ($recurringMessage->getMessages(new MessageContext('credit_request', $recurringMessage->getId(), $recurringMessage->getTrigger(), $after)) as $message) {
                self::assertInstanceOf(RedispatchMessage::class, $message);
                self::assertSame('async', $message->transportNames);
                $messages[] = $message->envelope::class;
            }
        }

        self::assertCount(6, $messages);
    }

    public function testRunsAtTheSameTimeWheneverTheWorkerStarted(): void
    {
        $schedule = new CreditRequestSchedule(new ArrayAdapter());

        $nextRuns = $this->nextRunsByMessage($schedule, new \DateTimeImmutable('tomorrow 01:17'));
        self::assertEquals(new \DateTimeImmutable('tomorrow 03:00'), $nextRuns[MarkOverdueInstallmentsMessage::class]);
        self::assertEquals(new \DateTimeImmutable('tomorrow 12:00'), $nextRuns[SendInstallmentDueDateRemindersMessage::class]);
        self::assertEquals(new \DateTimeImmutable('tomorrow 02:00'), $nextRuns[TimeoutInstallmentPaymentAttemptsMessage::class]);

        $nextRuns = $this->nextRunsByMessage($schedule, new \DateTimeImmutable('tomorrow 18:42'));
        self::assertEquals(new \DateTimeImmutable('+2 days 03:00'), $nextRuns[MarkOverdueInstallmentsMessage::class]);
        self::assertEquals(new \DateTimeImmutable('+2 days 12:00'), $nextRuns[SendInstallmentDueDateRemindersMessage::class]);
        self::assertEquals(new \DateTimeImmutable('tomorrow 19:00'), $nextRuns[TimeoutInstallmentPaymentAttemptsMessage::class]);
    }

    public function testARestartNeitherRepeatsNorPostponesTheDailyRun(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock(new \DateTimeImmutable('tomorrow 11:59:00'));

        // First worker: remembers the moment it started.
        self::assertSame([], $this->reminders($cache, $clock));

        // Restarted before the run: it still happens at 12:00, not 24 hours after the restart.
        $clock->modify('+2 minutes');
        self::assertCount(1, $this->reminders($cache, $clock));

        // Restarted right after the run: it isn't repeated.
        $clock->modify('+1 hour');
        self::assertSame([], $this->reminders($cache, $clock));
    }

    public function testARunMissedWhileTheWorkerWasDownHappensOnceWhenItComesBack(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock(new \DateTimeImmutable('tomorrow 11:59:00'));
        $this->reminders($cache, $clock);

        // Down for two 12:00 runs.
        $clock->modify('+2 days');

        self::assertCount(1, $this->reminders($cache, $clock));
    }

    /**
     * @return array<class-string, \DateTimeImmutable>
     */
    private function nextRunsByMessage(CreditRequestSchedule $schedule, \DateTimeImmutable $after): array
    {
        $nextRuns = [];
        foreach ($schedule->getSchedule()->getRecurringMessages() as $recurringMessage) {
            foreach ($recurringMessage->getMessages(new MessageContext('credit_request', $recurringMessage->getId(), $recurringMessage->getTrigger(), $after)) as $message) {
                $nextRuns[$message->envelope::class] = $recurringMessage->getTrigger()->getNextRunDate($after);
            }
        }

        return $nextRuns;
    }

    /**
     * Runs a new worker's generator, like a worker restart does, and returns the reminders it fires.
     *
     * @return RedispatchMessage[]
     */
    private function reminders(ArrayAdapter $cache, MockClock $clock): array
    {
        $generator = new MessageGenerator(new CreditRequestSchedule($cache), 'credit_request', $clock);

        $messages = [];
        foreach ($generator->getMessages() as $message) {
            $messages[] = $message;
        }

        return array_values(array_filter($messages, static fn (RedispatchMessage $message): bool => $message->envelope instanceof SendInstallmentDueDateRemindersMessage));
    }
}
