<?php

declare(strict_types=1);

namespace App\Tests\Functional\CreditRequest;

use App\Company\Domain\Model\Company;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\CreditRequestStatus;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\Domain\Model\InstallmentStatus;
use App\Tests\Functional\ApiAuthenticationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TotalDebtGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string URL = '/api/v1/company-total-debt';

    public function testReturnsSumOfOverdueAndPendingInstallmentsWithinRangeExcludingPaidAndOutOfRange(): void
    {
        $client = static::createClient();
        [$companyPayload, $token] = $this->registerAndLogin($client);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $company = $em->find(Company::class, $companyPayload['id']);

        $today = new \DateTimeImmutable('today');
        $limitDate = $today->modify('+2 months');
        $periodEnd = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $limitDate->format('Y'), (int) $limitDate->format('n'))))
            ->modify('first day of next month');

        $creditRequest = new CreditRequest(
            'aaaaaaaa-0000-4000-8000-000000000001',
            '100000',
            '55',
            3,
            $company,
            CreditRequestStatus::Active,
        );
        $em->persist($creditRequest);

        $overdue = $this->createInstallment('aaaaaaaa-0000-4000-8000-000000000002', 1, InstallmentStatus::Overdue, $creditRequest, $today->modify('-10 days'), '1000.00');
        $pendingInRange = $this->createInstallment('aaaaaaaa-0000-4000-8000-000000000003', 2, InstallmentStatus::Pending, $creditRequest, $today->modify('+15 days'), '2000.00');
        $pendingAtRangeEnd = $this->createInstallment('aaaaaaaa-0000-4000-8000-000000000004', 3, InstallmentStatus::Pending, $creditRequest, $periodEnd->modify('-1 day'), '3000.00');
        $pendingAfterRange = $this->createInstallment('aaaaaaaa-0000-4000-8000-000000000005', 4, InstallmentStatus::Pending, $creditRequest, $periodEnd, '4000.00');
        $paidInRange = $this->createInstallment('aaaaaaaa-0000-4000-8000-000000000006', 5, InstallmentStatus::Paid, $creditRequest, $today->modify('+5 days'), '5000.00');

        $em->persist($overdue);
        $em->persist($pendingInRange);
        $em->persist($pendingAtRangeEnd);
        $em->persist($pendingAfterRange);
        $em->persist($paidInRange);
        $em->flush();

        $client->jsonRequest('GET', self::URL . '?' . http_build_query([
            'month' => (int) $limitDate->format('n'),
            'year'  => (int) $limitDate->format('Y'),
        ]), [], self::bearer($token));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('6000.00', $response['totalAmount']);
    }

    public function testLimitMonthEqualToCurrentMonthOnlyIncludesPendingInstallmentsFromTodayOnward(): void
    {
        $client = static::createClient();
        [$companyPayload, $token] = $this->registerAndLogin($client);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $company = $em->find(Company::class, $companyPayload['id']);

        $today = new \DateTimeImmutable('today');
        $periodEnd = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $today->format('Y'), (int) $today->format('n'))))
            ->modify('first day of next month');

        $creditRequest = new CreditRequest(
            'bbbbbbbb-0000-4000-8000-000000000001',
            '100000',
            '55',
            3,
            $company,
            CreditRequestStatus::Active,
        );
        $em->persist($creditRequest);

        $pendingYesterday = $this->createInstallment('bbbbbbbb-0000-4000-8000-000000000002', 1, InstallmentStatus::Pending, $creditRequest, $today->modify('-1 day'), '1000.00');
        $overdueLongAgo = $this->createInstallment('bbbbbbbb-0000-4000-8000-000000000003', 2, InstallmentStatus::Overdue, $creditRequest, $today->modify('-30 days'), '2000.00');
        $pendingToday = $this->createInstallment('bbbbbbbb-0000-4000-8000-000000000004', 3, InstallmentStatus::Pending, $creditRequest, $today, '3000.00');
        $pendingMonthEnd = $this->createInstallment('bbbbbbbb-0000-4000-8000-000000000005', 4, InstallmentStatus::Pending, $creditRequest, $periodEnd->modify('-1 day'), '4000.00');
        $pendingNextMonth = $this->createInstallment('bbbbbbbb-0000-4000-8000-000000000006', 5, InstallmentStatus::Pending, $creditRequest, $periodEnd, '5000.00');

        $em->persist($pendingYesterday);
        $em->persist($overdueLongAgo);
        $em->persist($pendingToday);
        $em->persist($pendingMonthEnd);
        $em->persist($pendingNextMonth);
        $em->flush();

        $client->jsonRequest('GET', self::URL . '?' . http_build_query([
            'month' => (int) $today->format('n'),
            'year'  => (int) $today->format('Y'),
        ]), [], self::bearer($token));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('9000.00', $response['totalAmount']);
    }

    public function testReturnsZeroWhenNoQualifyingInstallmentsExist(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $today = new \DateTimeImmutable('today');

        $client->jsonRequest('GET', self::URL . '?' . http_build_query([
            'month' => (int) $today->format('n'),
            'year'  => (int) $today->format('Y'),
        ]), [], self::bearer($token));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('0.00', $response['totalAmount']);
    }

    public function testExcludesInstallmentsBelongingToAnotherCompany(): void
    {
        $client = static::createClient();
        [$firstCompanyPayload, $firstToken] = $this->registerAndLogin($client);
        [$secondCompanyPayload] = $this->registerAndLogin($client, [
            'id'           => 'cccccccc-0000-4000-8000-000000000099',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $firstCompany = $em->find(Company::class, $firstCompanyPayload['id']);
        $secondCompany = $em->find(Company::class, $secondCompanyPayload['id']);

        $today = new \DateTimeImmutable('today');

        $firstCreditRequest = new CreditRequest(
            'cccccccc-0000-4000-8000-000000000001',
            '100000',
            '55',
            3,
            $firstCompany,
            CreditRequestStatus::Active,
        );
        $em->persist($firstCreditRequest);
        $firstCompanyInstallment = $this->createInstallment('cccccccc-0000-4000-8000-000000000002', 1, InstallmentStatus::Pending, $firstCreditRequest, $today, '1000.00');
        $em->persist($firstCompanyInstallment);

        $secondCreditRequest = new CreditRequest(
            'cccccccc-0000-4000-8000-000000000003',
            '200000',
            '50',
            1,
            $secondCompany,
            CreditRequestStatus::Active,
        );
        $em->persist($secondCreditRequest);
        $secondCompanyInstallment = $this->createInstallment('cccccccc-0000-4000-8000-000000000004', 1, InstallmentStatus::Overdue, $secondCreditRequest, $today->modify('-5 days'), '9999.00');
        $em->persist($secondCompanyInstallment);

        $em->flush();

        $client->jsonRequest('GET', self::URL . '?' . http_build_query([
            'month' => (int) $today->format('n'),
            'year'  => (int) $today->format('Y'),
        ]), [], self::bearer($firstToken));

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('1000.00', $response['totalAmount']);
    }

    public function testInvalidMonthOrYearReturnsValidationError(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $client->jsonRequest('GET', self::URL . '?month=13&year=2026', [], self::bearer($token));
        self::assertResponseStatusCodeSame(422);

        $client->jsonRequest('GET', self::URL, [], self::bearer($token));
        self::assertResponseStatusCodeSame(422);
    }

    public function testLimitMonthBeforeCurrentMonthReturnsValidationError(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        $previousMonth = (new \DateTimeImmutable('today'))->modify('first day of previous month');

        $client->jsonRequest('GET', self::URL . '?' . http_build_query([
            'month' => (int) $previousMonth->format('n'),
            'year'  => (int) $previousMonth->format('Y'),
        ]), [], self::bearer($token));

        self::assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('month', $response['errors']);
    }

    private function createInstallment(
        string $id,
        int $periodNumber,
        InstallmentStatus $status,
        CreditRequest $creditRequest,
        \DateTimeImmutable $dueDate,
        string $totalAmount,
    ): Installment {
        $installment = new Installment(
            $id,
            $periodNumber,
            $totalAmount,
            '0.00',
            '0.00',
            $totalAmount,
            $status,
            $creditRequest,
        );
        $installment->changeDueDate($dueDate);

        return $installment;
    }
}
