<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\CreditRequest\Application\Message\MarkOverdueInstallmentsMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ScheduledTaskRedispatchTest extends KernelTestCase
{
    public function testAFiredTaskIsQueuedOnTheAsyncTransportInsteadOfRunningInline(): void
    {
        self::bootKernel();

        self::getContainer()->get(MessageBusInterface::class)->dispatch(
            new RedispatchMessage(new MarkOverdueInstallmentsMessage(), 'async'),
        );

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertCount(1, $transport->getSent());
        self::assertInstanceOf(MarkOverdueInstallmentsMessage::class, $transport->getSent()[0]->getMessage());
    }
}
