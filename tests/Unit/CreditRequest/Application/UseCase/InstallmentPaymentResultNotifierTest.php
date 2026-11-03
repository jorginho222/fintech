<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultOutcome;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultNotifier;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Service\InstallmentPaymentNotifierInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstallmentPaymentResultNotifierTest extends TestCase
{
    use InstallmentFixture;

    /**
     * @return iterable<string, array{InstallmentPaymentAttemptStatus, string}>
     */
    public static function appliedResults(): iterable
    {
        yield 'approved' => [InstallmentPaymentAttemptStatus::Approved, 'notifyPaymentApproved'];
        yield 'rejected' => [InstallmentPaymentAttemptStatus::Rejected, 'notifyPaymentRejected'];
        yield 'processing failed' => [InstallmentPaymentAttemptStatus::ProcessingFailed, 'notifyPaymentProcessingFailed'];
    }

    #[DataProvider('appliedResults')]
    public function testNotifiesTheAppliedResult(InstallmentPaymentAttemptStatus $status, string $method): void
    {
        $installment = $this->installment();

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method($method)->with($installment);
        $notifier->expects(self::never())->method('notifyCreditRequestPaid');

        (new InstallmentPaymentResultNotifier($notifier))->execute(new InstallmentPaymentResultOutcome($installment, $status, false));
    }

    public function testAlsoNotifiesTheCreditRequestWhenItBecamePaid(): void
    {
        $installment = $this->installment();

        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyPaymentApproved')->with($installment);
        $notifier->expects(self::once())->method('notifyCreditRequestPaid')->with($installment->getCreditRequest());

        (new InstallmentPaymentResultNotifier($notifier))->execute(
            new InstallmentPaymentResultOutcome($installment, InstallmentPaymentAttemptStatus::Approved, true),
        );
    }

    public function testNotifiesNothingForAnIgnoredResult(): void
    {
        $notifier = $this->createMock(InstallmentPaymentNotifierInterface::class);
        $notifier->expects(self::never())->method(self::anything());

        (new InstallmentPaymentResultNotifier($notifier))->execute(new InstallmentPaymentResultOutcome($this->installment(), null, false));
    }
}
