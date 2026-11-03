<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\Message\RequestInstallmentPaymentMessage;
use App\CreditRequest\Application\UseCase\InstallmentPaymentRequester;
use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class InstallmentPaymentRequesterTest extends TestCase
{
    use InstallmentFixture;

    public function testSavesTheAttemptThenQueuesTheRequestAndNotifies(): void
    {
        $installment = $this->installment();
        $calls = [];

        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);
        $repository->method('saveInstallment')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'save';
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (RequestInstallmentPaymentMessage $message): bool => $message->getInstallmentId() === $installment->getId()))
            ->willReturnCallback(static function (object $message) use (&$calls): Envelope {
                $calls[] = 'dispatch';

                return new Envelope($message);
            });

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyPaymentProcessing')->with($installment);

        $creditRequest = (new InstallmentPaymentRequester($repository, $this->companyIdProvider($installment->getCreditRequest()->getCompany()->getId()), $notifier, $bus))
            ->execute($installment->getId());

        self::assertSame($installment->getCreditRequest(), $creditRequest);
        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $installment->getLastPaymentAttemptStatus());
        self::assertSame(['save', 'dispatch'], $calls);
    }

    public function testDoesNotFindAnInstallmentOfAnotherCompany(): void
    {
        $installment = $this->installment();

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);
        $repository->expects(self::never())->method('saveInstallment');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(InstallmentNotFoundException::class);

        (new InstallmentPaymentRequester($repository, $this->companyIdProvider('dddddddd-dddd-4ddd-8ddd-dddddddddddd'), $this->createStub(InstallmentPaymentNotifierInterface::class), $bus))
            ->execute($installment->getId());
    }

    private function companyIdProvider(string $companyId): AuthenticatedCompanyIdProviderInterface
    {
        $provider = $this->createStub(AuthenticatedCompanyIdProviderInterface::class);
        $provider->method('getCompanyId')->willReturn($companyId);

        return $provider;
    }
}
