<?php

declare(strict_types=1);

namespace App\Tests\Integration\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Model\CreditRequestApplicationStatus;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCreditRequestApplicationRepositoryTest extends KernelTestCase
{
    private CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->creditRequestApplicationRepository = self::getContainer()->get(CreditRequestApplicationRepositoryInterface::class);
        $this->em                                 = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCountsTheApplicationsOfEveryStatus(): void
    {
        $company = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $expected = [];
        foreach (CreditRequestApplicationStatus::cases() as $index => $status) {
            // A different count per status, so a mixed-up key would not go unnoticed.
            for ($i = 0; $i <= $index; $i++) {
                $this->application(sprintf('%d%07d-1111-4111-8111-111111111111', $index, $i), $company, $status);
            }
            $expected[$status->value] = $index + 1;
        }
        $this->em->clear();

        $counts = $this->creditRequestApplicationRepository->countByStatus($company->getId());
        ksort($counts);
        ksort($expected);

        self::assertSame($expected, $counts);
    }

    public function testLeavesOutStatusesWithoutApplicationsAndOtherCompanies(): void
    {
        $companyA = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $companyB = $this->company('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '30987654321');
        $this->application('11111111-1111-4111-8111-111111111111', $companyA, CreditRequestApplicationStatus::Approved);
        $this->application('22222222-2222-4222-8222-222222222222', $companyB, CreditRequestApplicationStatus::Approved);
        $this->application('33333333-3333-4333-8333-333333333333', $companyB, CreditRequestApplicationStatus::Rejected);
        $this->em->clear();

        self::assertSame(
            [CreditRequestApplicationStatus::Approved->value => 1],
            $this->creditRequestApplicationRepository->countByStatus($companyA->getId()),
        );
    }

    public function testReturnsNoCountsForACompanyWithoutApplications(): void
    {
        $company = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');

        self::assertSame([], $this->creditRequestApplicationRepository->countByStatus($company->getId()));
    }

    private function company(string $id, string $cuit): Company
    {
        $company = new Company($id, 'Empresa ' . $cuit, $cuit, 'empresa' . $cuit . '@empresa.com', TaxStatus::Monotributo);
        self::getContainer()->get(CompanyRepositoryInterface::class)->save($company);

        return $company;
    }

    private function application(string $id, Company $company, CreditRequestApplicationStatus $status): void
    {
        $application = new CreditRequestApplication($id, 'Solicitud ' . $id, '10000000', 12, $company);
        match ($status) {
            CreditRequestApplicationStatus::EvaluationPending => null,
            CreditRequestApplicationStatus::Approved          => $application->approve(),
            CreditRequestApplicationStatus::Rejected          => $application->reject('CAPACIDAD_PAGO_EXCEDIDA'),
        };
        $this->creditRequestApplicationRepository->save($application);
    }
}
