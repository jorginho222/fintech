<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\Message\HandleCreditRequestApplicationResultMessage;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\Infrastructure\Security\CreditBureauWebhookAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class CreditRequestApplicationUpdatePutControllerTest extends WebTestCase
{
    private const string UPDATE_URL = '/api/v1/credit-request-application-update';

    public function testWebhookIsAcceptedAndQueuedWithoutExecutingSynchronously(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Empresa SRL',
            '20111111112',
            'empresa@empresa.com',
            TaxStatus::Monotributo,
            'hashed-password',
        );
        $em->persist($company);

        $creditRequestApplication = new CreditRequestApplication(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            '100000',
            6,
            $company,
        );
        $em->persist($creditRequestApplication);
        $em->flush();
        $em->clear();

        $client->jsonRequest('PUT', self::UPDATE_URL, [
            'applicationId' => $creditRequestApplication->getId(),
            'cuit'          => '20111111112',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'APPROVED',
                'score'           => 812,
                'assignedTna'     => 0.55,
                'rejectionReason' => null,
            ],
        ], [
            'HTTP_' . str_replace('-', '_', strtoupper(CreditBureauWebhookAuthenticator::HEADER)) => static::getContainer()->getParameter('app.credit_bureau_simulation.webhook_secret'),
        ]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame('{}', $client->getResponse()->getContent());

        $stillPendingApplication = $em->find(CreditRequestApplication::class, $creditRequestApplication->getId());
        self::assertSame(CreditRequestApplicationStatus::EvaluationPending, $stillPendingApplication->getStatus());

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $envelopes = $transport->getSent();
        self::assertCount(1, $envelopes);
        self::assertInstanceOf(HandleCreditRequestApplicationResultMessage::class, $envelopes[0]->getMessage());
    }

    public function testWebhookRejectsAnInvalidSharedSecret(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', self::UPDATE_URL, [
            'applicationId' => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'cuit'          => '20111111112',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'APPROVED',
                'score'           => 812,
                'assignedTna'     => 0.55,
                'rejectionReason' => null,
            ],
        ], [
            'HTTP_' . str_replace('-', '_', strtoupper(CreditBureauWebhookAuthenticator::HEADER)) => 'wrong-secret',
        ]);

        self::assertResponseStatusCodeSame(401);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }

    public function testWebhookRejectsAMalformedPayloadWithoutQueueingIt(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PUT', self::UPDATE_URL, [
            'applicationId' => 'not-a-uuid',
        ], [
            'HTTP_' . str_replace('-', '_', strtoupper(CreditBureauWebhookAuthenticator::HEADER)) => static::getContainer()->getParameter('app.credit_bureau_simulation.webhook_secret'),
        ]);

        self::assertResponseStatusCodeSame(422);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertCount(0, $transport->getSent());
    }
}
