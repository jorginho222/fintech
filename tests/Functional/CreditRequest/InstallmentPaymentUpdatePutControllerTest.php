<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\Message\HandleInstallmentPaymentResultMessage;
use App\CreditRequest\Application\MessageHandler\HandleInstallmentPaymentResultMessageHandler;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Infrastructure\Security\InstallmentPaymentGatewayWebhookAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class InstallmentPaymentUpdatePutControllerTest extends WebTestCase
{
    private const string URL            = '/api/v1/installment-payment-update';
    private const string INSTALLMENT_ID = '10000000-0000-4000-8000-000000000001';

    public function testValidResultIsAcceptedAndQueuedWithoutBeingApplied(): void
    {
        $client = static::createClient();
        $this->createProcessingInstallment();

        $this->sendResult($client, ['installmentId' => self::INSTALLMENT_ID, 'status' => 'APPROVED']);

        self::assertResponseStatusCodeSame(202);

        $installment = $this->freshInstallment();
        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $installment->getLastPaymentAttemptStatus());

        $messages = $this->queuedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(HandleInstallmentPaymentResultMessage::class, $messages[0]);
    }

    public function testRejectsAnInvalidSharedSecret(): void
    {
        $client = static::createClient();

        $this->sendResult($client, ['installmentId' => self::INSTALLMENT_ID, 'status' => 'APPROVED'], 'wrong-secret');

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->queuedMessages());
    }

    public function testRejectsTheCreditBureauSecret(): void
    {
        $client = static::createClient();
        $bureauSecret = self::getContainer()->getParameter('app.credit_bureau_simulation.webhook_secret');

        $this->sendResult($client, ['installmentId' => self::INSTALLMENT_ID, 'status' => 'APPROVED'], $bureauSecret);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedResults(): iterable
    {
        yield 'invalid status' => [['installmentId' => self::INSTALLMENT_ID, 'status' => 'PAID']];
        yield 'missing installment id' => [['status' => 'APPROVED']];
        yield 'invalid installment id' => [['installmentId' => 'not-a-uuid', 'status' => 'APPROVED']];
        yield 'processing failed without error message' => [['installmentId' => self::INSTALLMENT_ID, 'status' => 'PROCESSING_FAILED']];
        yield 'processing failed with a too long error message' => [['installmentId' => self::INSTALLMENT_ID, 'status' => 'PROCESSING_FAILED', 'errorMessage' => str_repeat('a', 1001)]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('malformedResults')]
    public function testRejectsAMalformedResultWithoutQueueingIt(array $payload): void
    {
        $client = static::createClient();

        $this->sendResult($client, $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->queuedMessages());
    }

    public function testApprovedResultPaysTheCreditRequestAndQueuesBothEmails(): void
    {
        static::createClient();
        $this->createProcessingInstallment();

        $this->handleResult(['installmentId' => self::INSTALLMENT_ID, 'status' => 'APPROVED']);

        $installment = $this->freshInstallment();
        self::assertSame(InstallmentStatus::Paid, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $installment->getLastPaymentAttemptStatus());
        self::assertSame(CreditRequestStatus::Paid, $installment->getCreditRequest()->getStatus());
        self::assertSame(['Pago aprobado', 'Crédito pagado en su totalidad'], $this->queuedEmailSubjects());
    }

    public function testProcessingFailedResultStoresTheErrorMessageAndQueuesItsEmail(): void
    {
        static::createClient();
        $this->createProcessingInstallment();

        $this->handleResult(['installmentId' => self::INSTALLMENT_ID, 'status' => 'PROCESSING_FAILED', 'errorMessage' => 'Gateway error']);

        $installment = $this->freshInstallment();
        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::ProcessingFailed, $installment->getLastPaymentAttemptStatus());
        self::assertSame('Gateway error', $installment->getLastPaymentAttemptErrorMessage());
        self::assertSame(['No se pudo procesar el pago'], $this->queuedEmailSubjects());
    }

    public function testADuplicatedResultChangesNothingAndQueuesNoEmail(): void
    {
        static::createClient();
        $this->createProcessingInstallment();

        $this->handleResult(['installmentId' => self::INSTALLMENT_ID, 'status' => 'APPROVED']);
        $this->handleResult(['installmentId' => self::INSTALLMENT_ID, 'status' => 'REJECTED']);

        self::assertSame(InstallmentPaymentAttemptStatus::Approved, $this->freshInstallment()->getLastPaymentAttemptStatus());
        self::assertSame(['Pago aprobado', 'Crédito pagado en su totalidad'], $this->queuedEmailSubjects());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendResult(KernelBrowser $client, array $payload, ?string $secret = null): void
    {
        $secret ??= self::getContainer()->getParameter('app.payment_gateway_simulation.webhook_secret');

        $client->jsonRequest('PUT', self::URL, $payload, [
            'HTTP_' . str_replace('-', '_', strtoupper(InstallmentPaymentGatewayWebhookAuthenticator::HEADER)) => $secret,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handleResult(array $payload): void
    {
        $handler = self::getContainer()->get(HandleInstallmentPaymentResultMessageHandler::class);
        $handler(new HandleInstallmentPaymentResultMessage(json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /**
     * @return object[]
     */
    private function queuedMessages(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent());
    }

    /**
     * @return string[]
     */
    private function queuedEmailSubjects(): array
    {
        return array_map(
            static fn (SendEmailMessage $message): ?string => $message->getMessage()->getSubject(),
            array_values(array_filter($this->queuedMessages(), static fn (object $message): bool => $message instanceof SendEmailMessage)),
        );
    }

    private function freshInstallment(): Installment
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->find(Installment::class, self::INSTALLMENT_ID);
    }

    private function createProcessingInstallment(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'Empresa SRL', '20111111112', 'empresa@empresa.com', TaxStatus::Monotributo);
        $em->persist($company);

        $creditRequest = new CreditRequest('10000000-0000-4000-8000-000000000000', 'Crédito 1', '100000', '55', 1, $company, CreditRequestStatus::Active);
        $em->persist($creditRequest);

        $installment = new Installment(self::INSTALLMENT_ID, 1, '1000.00', '0.00', '0.00', '1000.00', InstallmentStatus::Pending, $creditRequest);
        $installment->changeDueDate(new \DateTimeImmutable('+10 days'));
        $installment->startPaymentAttempt(new \DateTimeImmutable());
        $creditRequest->addInstallment($installment);
        $em->persist($installment);
        $em->flush();
    }
}
