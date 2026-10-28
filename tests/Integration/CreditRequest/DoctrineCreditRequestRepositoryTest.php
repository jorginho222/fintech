<?php

declare(strict_types=1);

namespace App\Tests\Integration\CreditRequest;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCreditRequestRepositoryTest extends KernelTestCase
{
    private CreditRequestRepositoryInterface $creditRequestRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->creditRequestRepository = self::getContainer()->get(CreditRequestRepositoryInterface::class);
        $this->em                      = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCountsTheCompanysCreditRequestsInAStatus(): void
    {
        $companyA = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $companyB = $this->company('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '30987654321');
        $this->creditRequest('10000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Active);
        $this->creditRequest('20000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Active);
        $this->creditRequest('30000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Paid);
        $this->creditRequest('40000000-0000-4000-8000-000000000000', $companyB, CreditRequestStatus::Active);
        $this->em->clear();

        self::assertSame(2, $this->creditRequestRepository->countByCompanyAndStatus($companyA->getId(), CreditRequestStatus::Active));
        self::assertSame(1, $this->creditRequestRepository->countByCompanyAndStatus($companyA->getId(), CreditRequestStatus::Paid));
        self::assertSame(0, $this->creditRequestRepository->countByCompanyAndStatus($companyA->getId(), CreditRequestStatus::Proposal));
    }

    public function testCountsInstallmentsPerStatusOfTheCompanysCreditRequestsInAStatus(): void
    {
        $companyA = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');
        $companyB = $this->company('bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb', '30987654321');
        $this->creditRequest('10000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
            InstallmentStatus::Pending,
            InstallmentStatus::Pending,
        ]);
        $this->creditRequest('20000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Active, [
            InstallmentStatus::Paid,
            InstallmentStatus::Pending,
        ]);
        $this->creditRequest('30000000-0000-4000-8000-000000000000', $companyA, CreditRequestStatus::Paid, [
            InstallmentStatus::Paid,
        ]);
        $this->creditRequest('40000000-0000-4000-8000-000000000000', $companyB, CreditRequestStatus::Active, [
            InstallmentStatus::Overdue,
        ]);
        $this->em->clear();

        $counts = $this->creditRequestRepository->countInstallmentsByStatus($companyA->getId(), CreditRequestStatus::Active);
        ksort($counts);

        // No overdue key: statuses without installments are left out.
        self::assertSame(
            [InstallmentStatus::Paid->value => 2, InstallmentStatus::Pending->value => 3],
            $counts,
        );
    }

    public function testReturnsNoInstallmentCountsWhenThereAreNoCreditRequestsInTheStatus(): void
    {
        $company = $this->company('aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', '20123456789');

        self::assertSame([], $this->creditRequestRepository->countInstallmentsByStatus($company->getId(), CreditRequestStatus::Active));
    }

    private function company(string $id, string $cuit): Company
    {
        $company = new Company($id, 'Empresa ' . $cuit, $cuit, 'empresa' . $cuit . '@empresa.com', TaxStatus::Monotributo);
        self::getContainer()->get(CompanyRepositoryInterface::class)->save($company);

        return $company;
    }

    /**
     * @param InstallmentStatus[] $installmentStatuses
     */
    private function creditRequest(string $id, Company $company, CreditRequestStatus $status, array $installmentStatuses = []): void
    {
        $creditRequest = new CreditRequest($id, '100000', '55', max(1, count($installmentStatuses)), $company, $status);
        $this->em->persist($creditRequest);

        foreach ($installmentStatuses as $index => $installmentStatus) {
            $this->em->persist(new Installment(
                substr($id, 0, -12) . sprintf('%012d', $index + 1),
                $index + 1,
                '1000.00',
                '0.00',
                '0.00',
                '1000.00',
                $installmentStatus,
                $creditRequest,
            ));
        }

        $this->em->flush();
    }
}
