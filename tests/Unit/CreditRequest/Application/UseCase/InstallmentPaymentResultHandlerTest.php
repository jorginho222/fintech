<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Application\UseCase;

use App\CreditRequest\Application\DTO\InstallmentPaymentResultDto;
use App\CreditRequest\Application\UseCase\CreditRequestPaidChecker;
use App\CreditRequest\Application\UseCase\InstallmentPaymentResultHandler;
use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Tests\Unit\CreditRequest\InstallmentFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

final class InstallmentPaymentResultHandlerTest extends TestCase
{
    use InstallmentFixture;

    public function testApprovedPaysTheInstallmentAndReportsTheCreditRequestStillUnpaid(): void
    {
        $creditRequest = $this->creditRequest();
        $installment = $this->processingInstallment($creditRequest, 1);
        $this->installment(creditRequest: $creditRequest, periodNumber: 2);

        $outcome = $this->handler($installment)->execute($this->paymentResult($installment, 'APPROVED'));

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $outcome->appliedAttemptStatus);
        self::assertFalse($outcome->creditRequestPaid);
        self::assertSame(CreditRequestStatus::Active, $creditRequest->getStatus());
    }

    public function testApprovingTheLastUnpaidInstallmentPaysTheCreditRequest(): void
    {
        $creditRequest = $this->creditRequest();
        $this->installment(InstallmentStatus::Paid, $creditRequest, 1);
        $installment = $this->processingInstallment($creditRequest, 2);

        $outcome = $this->handler($installment)->execute($this->paymentResult($installment, 'APPROVED'));

        self::assertTrue($outcome->creditRequestPaid);
        self::assertSame(CreditRequestStatus::Paid, $creditRequest->getStatus());
    }

    public function testALateApprovalAfterTheTimeoutStillPays(): void
    {
        $installment = $this->processingInstallment();
        $installment->timeoutPaymentAttempt(new \DateTimeImmutable());

        $outcome = $this->handler($installment)->execute($this->paymentResult($installment, 'APPROVED'));

        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $outcome->appliedAttemptStatus);
    }

    public function testRejectedOnlyUpdatesTheAttempt(): void
    {
        $installment = $this->processingInstallment();

        $outcome = $this->handler($installment)->execute($this->paymentResult($installment, 'REJECTED'));

        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Rejected, $outcome->appliedAttemptStatus);
        self::assertFalse($outcome->creditRequestPaid);
    }

    public function testProcessingFailedStoresTheErrorMessage(): void
    {
        $installment = $this->processingInstallment();

        $outcome = $this->handler($installment)->execute($this->paymentResult($installment, 'PROCESSING_FAILED', 'Gateway error'));

        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::ProcessingFailed, $outcome->appliedAttemptStatus);
        self::assertSame('Gateway error', $installment->getLastPaymentAttemptErrorMessage());
    }

    public function testAResultForAnAlreadyPaidInstallmentWritesNothing(): void
    {
        $installment = $this->processingInstallment();
        $installment->approvePaymentAttempt(new \DateTimeImmutable());

        $repository = $this->createMock(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);
        $repository->expects(self::never())->method('saveInstallment');
        $repository->expects(self::never())->method('save');

        $outcome = (new InstallmentPaymentResultHandler($repository, new CreditRequestPaidChecker($repository)))
            ->execute($this->paymentResult($installment, 'REJECTED'));

        self::assertNull($outcome->appliedAttemptStatus);
        self::assertFalse($outcome->creditRequestPaid);
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $installment->getLastPaymentAttemptStatus());
    }

    public function testAnUnknownInstallmentThrows(): void
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn(null);

        $this->expectException(InstallmentNotFoundException::class);

        (new InstallmentPaymentResultHandler($repository, new CreditRequestPaidChecker($repository)))
            ->execute($this->paymentResult($this->installment(), 'APPROVED'));
    }

    private function processingInstallment(?CreditRequest $creditRequest = null, int $periodNumber = 3): Installment
    {
        $installment = $this->installment(creditRequest: $creditRequest, periodNumber: $periodNumber);
        $installment->startPaymentAttempt(new \DateTimeImmutable('-1 hour'));

        return $installment;
    }

    private function handler(Installment $installment): InstallmentPaymentResultHandler
    {
        $repository = $this->createStub(CreditRequestRepositoryInterface::class);
        $repository->method('findInstallmentById')->willReturn($installment);

        return new InstallmentPaymentResultHandler($repository, new CreditRequestPaidChecker($repository));
    }

    private function paymentResult(Installment $installment, string $status, ?string $errorMessage = null): InstallmentPaymentResultDto
    {
        return new InstallmentPaymentResultDto(
            new Request(content: json_encode(['installmentId' => $installment->getId(), 'status' => $status, 'errorMessage' => $errorMessage], JSON_THROW_ON_ERROR)),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }
}
