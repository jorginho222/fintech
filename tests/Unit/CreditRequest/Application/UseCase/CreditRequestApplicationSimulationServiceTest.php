<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationSimulationService;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Service\CreditRequestApplicationNotifierInterface;
use App\CreditRequest\Domain\Service\CreditRequestApplicationWebhookClientInterface;
use PHPUnit\Framework\TestCase;

final class CreditRequestApplicationSimulationServiceTest extends TestCase
{
    public function testSendsTheRequestAndThenNotifiesTheCompany(): void
    {
        $application = $this->application();
        $calls = [];

        $client = $this->createMock(CreditRequestApplicationWebhookClientInterface::class);
        $client->expects(self::once())
            ->method('sendRequest')
            ->with($application->getId(), '20123456789', '1000000.00')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'send';
            });

        $notifier = $this->createMock(CreditRequestApplicationNotifierInterface::class);
        $notifier->expects(self::once())
            ->method('notifyRequestSent')
            ->with($application)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'notify';
            });

        (new CreditRequestApplicationSimulationService($client, $notifier))->execute($application);

        self::assertSame(['send', 'notify'], $calls);
    }

    public function testDoesNotNotifyWhenTheRequestFails(): void
    {
        $client = $this->createStub(CreditRequestApplicationWebhookClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Service unavailable'));

        $notifier = $this->createMock(CreditRequestApplicationNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyRequestSent');

        $this->expectException(\RuntimeException::class);

        (new CreditRequestApplicationSimulationService($client, $notifier))->execute($this->application());
    }

    private function application(): CreditRequestApplication
    {
        return new CreditRequestApplication(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Ampliación planta',
            '1000000.00',
            12,
            new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20123456789', 'empresa@example.com', TaxStatus::Monotributo),
        );
    }
}
