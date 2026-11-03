<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\RequestInstallmentPaymentMessage;
use App\CreditRequest\Application\MessageHandler\RequestInstallmentPaymentMessageHandler;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentPaymentWebhookClientInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;

final class RequestInstallmentPaymentMessageHandlerTest extends TestCase
{
    use InstallmentFixture;

    public function testSendsTheRequestWhileThePaymentIsProcessing(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt(new \DateTimeImmutable());

        $client = $this->createMock(InstallmentPaymentWebhookClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->with($installment->getId(), '20123456780', '893833.3300');

        $this->handle($installment, $client);
    }

    public function testSendsNothingWhenTheAttemptIsNoLongerProcessing(): void
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt(new \DateTimeImmutable());
        $installment->timeoutPaymentAttempt(new \DateTimeImmutable());

        $client = $this->createMock(InstallmentPaymentWebhookClientInterface::class);
        $client->expects(self::never())->method('sendRequest');

        $this->handle($installment, $client);
    }

    public function testSendsNothingWhenTheInstallmentNoLongerExists(): void
    {
        $client = $this->createMock(InstallmentPaymentWebhookClientInterface::class);
        $client->expects(self::never())->method('sendRequest');

        $this->handle(null, $client);
    }

    private function handle(?Installment $installment, InstallmentPaymentWebhookClientInterface $client): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);

        (new RequestInstallmentPaymentMessageHandler($repository, $client))(new RequestInstallmentPaymentMessage('cccccccc-cccc-4ccc-8ccc-000000000003'));
    }
}
