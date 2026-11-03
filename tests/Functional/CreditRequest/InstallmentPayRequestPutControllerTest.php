<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Application\Message\RequestInstallmentPaymentMessage;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentPaymentAttemptStatus;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class InstallmentPayRequestPutControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    public function testMarksTheAttemptAsProcessingAndQueuesTheRequestAndTheEmail(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $installmentId = $this->createInstallment($company['id']);

        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($token));

        self::assertResponseStatusCodeSame(202);
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('active', $response['status']);
        self::assertSame('pending', $response['installments'][0]['status']);
        self::assertSame('processing', $response['installments'][0]['lastPaymentAttemptStatus']);
        self::assertNotNull($response['installments'][0]['lastPaymentAttemptAt']);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $installment = $em->find(Installment::class, $installmentId);
        self::assertSame(InstallmentStatus::Pending, $installment->getStatus());
        self::assertSame(InstallmentPaymentAttemptStatus::Processing, $installment->getLastPaymentAttemptStatus());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent());
        self::assertCount(2, $messages);
        self::assertInstanceOf(RequestInstallmentPaymentMessage::class, $messages[0]);
        self::assertSame($installmentId, $messages[0]->getInstallmentId());
        self::assertInstanceOf(SendEmailMessage::class, $messages[1]);

        /** @var Email $email */
        $email = $messages[1]->getMessage();
        self::assertSame($company['email'], $email->getTo()[0]->getAddress());
        self::assertSame('Pago en proceso', $email->getSubject());
    }

    public function testRefusesAnotherPaymentWhileOneIsProcessing(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $installmentId = $this->createInstallment($company['id']);

        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($token));
        self::assertResponseStatusCodeSame(202);

        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($token));

        self::assertResponseStatusCodeSame(409);
        self::assertSame(
            ['error' => 'El pago de esta cuota está siendo procesado. Espere la confirmación antes de volver a intentarlo.'],
            json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testRefusesAnAlreadyPaidInstallment(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $installmentId = $this->createInstallment($company['id'], InstallmentStatus::Paid);

        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($token));

        self::assertResponseStatusCodeSame(409);
    }

    public function testDoesNotFindAnInstallmentOfAnotherCompany(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);
        [$otherCompany] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);
        $installmentId = $this->createInstallment($otherCompany['id']);

        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($token));

        self::assertResponseStatusCodeSame(404);
    }

    public function testRequiresTheInstallmentPayPermission(): void
    {
        $client = static::createClient();
        [$company, $adminToken] = $this->registerAndLogin($client);
        $installmentId = $this->createInstallment($company['id']);

        $deniedToken = $this->createMemberAndLogin($client, $adminToken, ['total_debt.view']);
        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($deniedToken));
        self::assertResponseStatusCodeSame(403);

        $allowedToken = $this->createMemberAndLogin($client, $adminToken, ['installment.pay'], [
            'id'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'taxIdentifier' => '27666777888',
        ]);
        $client->jsonRequest('PUT', $this->url($installmentId), [], self::bearer($allowedToken));
        self::assertResponseStatusCodeSame(202);
    }

    private function url(string $installmentId): string
    {
        return sprintf('/api/v1/installment/%s/pay-request', $installmentId);
    }

    private function createInstallment(string $companyId, InstallmentStatus $status = InstallmentStatus::Pending): string
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $creditRequest = new CreditRequest('10000000-0000-4000-8000-000000000000', 'Crédito 1', '100000', '55', 1, $em->find(Company::class, $companyId), CreditRequestStatus::Active);
        $em->persist($creditRequest);

        $installment = new Installment('10000000-0000-4000-8000-000000000001', 1, '1000.00', '0.00', '0.00', '1000.00', $status, $creditRequest);
        $installment->changeDueDate(new \DateTimeImmutable('+10 days'));
        $creditRequest->addInstallment($installment);
        $em->persist($installment);
        $em->flush();

        return $installment->getId();
    }
}
