<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationResultHandler;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
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

    private function buildResultDto(array $payload): CreditRequestApplicationResultDto
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $request = new Request([], [], [], [], [], [], json_encode($payload, JSON_THROW_ON_ERROR));

        return new CreditRequestApplicationResultDto($request, $validator);
    }
}
