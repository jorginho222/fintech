<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\Message\HandleCreditRequestApplicationResultMessage;
use App\CreditRequest\Application\MessageHandler\HandleCreditRequestApplicationResultMessageHandler;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultHandler;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultNotifier;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\UI\Api\Serializer\CreditRequestApplicationSerializer;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestApplicationResultHandlerTest extends KernelTestCase
{
    public function testApprovedDecisionUpdatesCompanyAndApplicationAndLinksANewCreditRequest(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company(
            'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            'Aprobada SRL',
            '20111111112',
            'aprobada@empresa.com',
            TaxStatus::Monotributo,
        );
        $em->persist($company);

        $creditRequestApplication = new CreditRequestApplication(
            'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'Crédito 1',
            '100000',
            6,
            $company,
        );
        $em->persist($creditRequestApplication);
        $em->flush();
        $em->clear();

        $dto = $this->buildResultDto([
            'applicationId' => $creditRequestApplication->getId(),
            'cuit'          => '20111111112',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'APPROVED',
                'score'           => 812,
                'assignedTna'     => 0.55,
                'rejectionReason' => null,
            ],
        ]);

        $handler = self::getContainer()->get(CreditRequestApplicationResultHandler::class);
        $handler->execute($dto);
        $em->clear();

        $updatedApplication = $em->find(CreditRequestApplication::class, $creditRequestApplication->getId());
        self::assertSame(CreditRequestApplicationStatus::Approved, $updatedApplication->getStatus());
        self::assertNull($updatedApplication->getRejectionReason());
        self::assertNotNull($updatedApplication->getCreditRequest());
        self::assertSame('Crédito 1', $updatedApplication->getCreditRequest()->getReference());

        $serializedApplication = (new CreditRequestApplicationSerializer())->serialize($updatedApplication);
        self::assertSame([
            'id' => $updatedApplication->getCreditRequest()->getId(),
            'reference' => 'Crédito 1',
            'status' => 'proposal',
        ], $serializedApplication['creditRequest']);

        $updatedCompany = $em->find(Company::class, $company->getId());
        self::assertSame(812, $updatedCompany->getScore());
    }

    public function testRejectedDecisionUpdatesCompanyAndApplicationWithoutCreatingACreditRequest(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $company = new Company(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Rechazada SRL',
            '20222222223',
            'rechazada@empresa.com',
            TaxStatus::Monotributo,
        );
        $em->persist($company);

        $creditRequestApplication = new CreditRequestApplication(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Crédito 2',
            '100000',
            6,
            $company,
        );
        $em->persist($creditRequestApplication);
        $em->flush();
        $em->clear();

        $dto = $this->buildResultDto([
            'applicationId' => $creditRequestApplication->getId(),
            'cuit'          => '20222222223',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'REJECTED',
                'score'           => 480,
                'assignedTna'     => null,
                'rejectionReason' => 'CAPACIDAD_PAGO_EXCEDIDA',
            ],
        ]);

        $handler = self::getContainer()->get(CreditRequestApplicationResultHandler::class);
        $handler->execute($dto);
        $em->clear();

        $updatedApplication = $em->find(CreditRequestApplication::class, $creditRequestApplication->getId());
        self::assertSame(CreditRequestApplicationStatus::Rejected, $updatedApplication->getStatus());
        self::assertSame('CAPACIDAD_PAGO_EXCEDIDA', $updatedApplication->getRejectionReason());
        self::assertNull($updatedApplication->getCreditRequest());

        $updatedCompany = $em->find(Company::class, $company->getId());
        self::assertSame(480, $updatedCompany->getScore());
    }

    public function testMessageHandlerQueuesTheResultEmailToTheCompany(): void
    {
        self::bootKernel();
        $this->persistApplication('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');

        self::getContainer()->get(HandleCreditRequestApplicationResultMessageHandler::class)(
            new HandleCreditRequestApplicationResultMessage($this->rejectedPayload('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee')),
        );

        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertSame('Solicitud de crédito rechazada', $email->getSubject());
        self::assertSame('resultado@empresa.com', $email->getTo()[0]->getAddress());
    }

    public function testMessageHandlerQueuesNoEmailWhenTheTransactionFails(): void
    {
        self::bootKernel();
        $this->persistApplication('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');

        $transactionManager = $this->createStub(TransactionManagerInterface::class);
        $transactionManager->method('transactional')->willThrowException(new \RuntimeException('Commit failed'));

        $handler = new HandleCreditRequestApplicationResultMessageHandler(
            self::getContainer()->get(CreditRequestApplicationResultHandler::class),
            self::getContainer()->get(CreditRequestApplicationResultNotifier::class),
            self::getContainer()->get(ValidatorInterface::class),
            $transactionManager,
        );

        try {
            $handler(new HandleCreditRequestApplicationResultMessage($this->rejectedPayload('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee')));
            self::fail('The transaction failure should reach Messenger.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Commit failed', $exception->getMessage());
        }

        self::assertQueuedEmailCount(0);
    }

    public function testRepeatedRejectedDecisionIsIgnoredAndSendsOneEmail(): void
    {
        self::bootKernel();
        $id = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
        $this->persistApplication($id);
        $handler = self::getContainer()->get(HandleCreditRequestApplicationResultMessageHandler::class);

        $handler(new HandleCreditRequestApplicationResultMessage($this->rejectedPayload($id)));
        $handler(new HandleCreditRequestApplicationResultMessage($this->rejectedPayload($id)));

        self::assertQueuedEmailCount(1);
    }

    public function testRepeatedApprovedDecisionIsIgnoredAndCreatesOneCreditRequest(): void
    {
        self::bootKernel();
        $id = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
        $this->persistApplication($id);
        $handler = self::getContainer()->get(HandleCreditRequestApplicationResultMessageHandler::class);

        $handler(new HandleCreditRequestApplicationResultMessage($this->approvedPayload($id)));
        $handler(new HandleCreditRequestApplicationResultMessage($this->approvedPayload($id)));

        self::assertQueuedEmailCount(1);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertCount(1, $em->getRepository(CreditRequest::class)->findBy(['reference' => 'Crédito 3']));
    }

    public function testDecisionForAnAlreadyDecidedApplicationChangesNothing(): void
    {
        self::bootKernel();
        $id = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
        $this->persistApplication($id);
        $handler = self::getContainer()->get(HandleCreditRequestApplicationResultMessageHandler::class);

        $handler(new HandleCreditRequestApplicationResultMessage($this->rejectedPayload($id)));
        $handler(new HandleCreditRequestApplicationResultMessage($this->approvedPayload($id)));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $application = $em->find(CreditRequestApplication::class, $id);
        self::assertSame(CreditRequestApplicationStatus::Rejected, $application->getStatus());
        self::assertSame('CAPACIDAD_PAGO_EXCEDIDA', $application->getRejectionReason());
        self::assertNull($application->getCreditRequest());
        self::assertSame(480, $em->find(Company::class, 'ffffffff-ffff-4fff-8fff-ffffffffffff')->getScore());
        self::assertQueuedEmailCount(1);
    }

    private function persistApplication(string $id): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $company = new Company('ffffffff-ffff-4fff-8fff-ffffffffffff', 'Resultado SRL', '20333333334', 'resultado@empresa.com', TaxStatus::Monotributo);
        $em->persist($company);
        $em->persist(new CreditRequestApplication($id, 'Crédito 3', '100000', 6, $company));
        $em->flush();
    }

    private function approvedPayload(string $id): string
    {
        return json_encode([
            'applicationId' => $id,
            'cuit'          => '20333333334',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'APPROVED',
                'score'           => 812,
                'assignedTna'     => 0.55,
                'rejectionReason' => null,
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function rejectedPayload(string $id): string
    {
        return json_encode([
            'applicationId' => $id,
            'cuit'          => '20333333334',
            'evaluatedAt'   => '2026-08-07T14:32:00+00:00',
            'decision'      => [
                'status'          => 'REJECTED',
                'score'           => 480,
                'assignedTna'     => null,
                'rejectionReason' => 'CAPACIDAD_PAGO_EXCEDIDA',
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function buildResultDto(array $payload): CreditRequestApplicationResultDto
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $request = new Request([], [], [], [], [], [], json_encode($payload, JSON_THROW_ON_ERROR));

        return new CreditRequestApplicationResultDto($request, $validator);
    }
}
