<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\HandleInstallmentPaymentResultMessage;
use App\CreditRequest\Application\MessageHandler\HandleInstallmentPaymentResultMessageHandler;
use App\CreditRequest\Application\UseCase\CreditRequestPaidChecker;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultHandler;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultNotifier;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Shared\Domain\Service\TransactionManagerInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class HandleInstallmentPaymentResultMessageHandlerTest extends TestCase
{
    use InstallmentFixture;

    public function testNotifiesOnceTheTransactionCommitted(): void
    {
        $installment = $this->processingInstallment();

        $transactionManager = $this->createStub(TransactionManagerInterface::class);
        $transactionManager->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyPaymentRejected')->with($installment);

        $this->handler($installment, $transactionManager, $notifier)($this->message($installment));
    }

    public function testDoesNotNotifyWhenTheTransactionFails(): void
    {
        $installment = $this->processingInstallment();

        $transactionManager = $this->createStub(TransactionManagerInterface::class);
        $transactionManager->method('transactional')->willThrowException(new \RuntimeException('Commit failed'));

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::never())->method(self::anything());

        $this->expectException(\RuntimeException::class);

        $this->handler($installment, $transactionManager, $notifier)($this->message($installment));
    }

    private function processingInstallment(): Installment
    {
        $installment = $this->installment();
        $installment->startPaymentAttempt(new \DateTimeImmutable('-1 hour'));

        return $installment;
    }

    private function message(Installment $installment): HandleInstallmentPaymentResultMessage
    {
        return new HandleInstallmentPaymentResultMessage(json_encode(['installmentId' => $installment->getId(), 'status' => 'REJECTED'], JSON_THROW_ON_ERROR));
    }

    private function handler(
        Installment $installment,
        TransactionManagerInterface $transactionManager,
        InstallmentPaymentNotifierInterface $notifier,
    ): HandleInstallmentPaymentResultMessageHandler {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);

        return new HandleInstallmentPaymentResultMessageHandler(
            new InstallmentPaymentResultHandler($repository, new CreditRequestPaidChecker($repository)),
            new InstallmentPaymentResultNotifier($notifier),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $transactionManager,
        );
    }
}
