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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InstallmentPaymentsDashboardGetControllerTest extends WebTestCase
{
    use ApiAuthenticationTrait;

    private const string URL = '/api/v1/installment/dashboard-payments';

    public function testListsPendingPaymentsOfTheMonthAndOverduePaymentsSeparately(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        $active = $this->creditRequest('10000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Active);
        $overduePreviousMonth = $this->installment($active, 1, InstallmentStatus::Overdue, $monthStart->modify('-1 month'), '200.00');
        $overdueThisMonth     = $this->installment($active, 2, InstallmentStatus::Overdue, $monthStart, '300.00');
        $pendingLastDay       = $this->installment($active, 3, InstallmentStatus::Pending, $monthStart->modify('last day of this month'), '2000.00');
        $pendingFirstDay      = $this->installment($active, 4, InstallmentStatus::Pending, $monthStart, '1000.00');
        $this->installment($active, 5, InstallmentStatus::Pending, $monthStart->modify('first day of next month'), '5000.00');
        $this->installment($active, 6, InstallmentStatus::Paid, $monthStart->modify('+5 days'), '7000.00');

        $paidCredit = $this->creditRequest('20000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Paid);
        $this->installment($paidCredit, 1, InstallmentStatus::Pending, $monthStart->modify('+5 days'), '9000.00');
        $this->installment($paidCredit, 2, InstallmentStatus::Overdue, $monthStart->modify('-1 month'), '9000.00');
        $this->flush();

        $payments = $this->requestPayments($client, $token);

        self::assertSame([$pendingFirstDay->getId(), $pendingLastDay->getId()], $this->ids($payments['monthPayments']));
        self::assertSame('3000.00', $payments['monthPayments']['totalAmount']);
        self::assertSame([$overduePreviousMonth->getId(), $overdueThisMonth->getId()], $this->ids($payments['overduePayments']));
        self::assertSame('500.00', $payments['overduePayments']['totalAmount']);
    }

    public function testEachPaymentHasWhatTheDashboardShows(): void
    {
        $client = static::createClient();
        [$company, $token] = $this->registerAndLogin($client);
        $dueDate = new \DateTimeImmutable('first day of this month midnight');

        $creditRequest = $this->creditRequest('10000000-0000-4000-8000-000000000000', $company['id'], CreditRequestStatus::Active);
        $this->installment($creditRequest, 1, InstallmentStatus::Pending, $dueDate, '1000.00');
        $this->flush();

        $payment = $this->requestPayments($client, $token)['monthPayments']['installments'][0];

        self::assertSame(1, $payment['periodNumber']);
        self::assertSame(12, $payment['creditRequestInstallmentQuantity']);
        self::assertSame('Crédito 10000000-0000-4000-8000-000000000000', $payment['creditRequestReference']);
        self::assertSame('10000000-0000-4000-8000-000000000000', $payment['creditRequestId']);
        self::assertSame($dueDate->format(\DateTimeInterface::ATOM), $payment['dueDate']);
        self::assertSame(1000.0, (float) $payment['totalAmount']);
    }

    public function testReturnsEmptyListsWithZeroTotalsWhenThereIsNothingToPay(): void
    {
        $client = static::createClient();
        [, $token] = $this->registerAndLogin($client);

        self::assertSame([
            'monthPayments'   => ['totalAmount' => '0.00', 'installments' => []],
            'overduePayments' => ['totalAmount' => '0.00', 'installments' => []],
        ], $this->requestPayments($client, $token));
    }

    public function testExcludesInstallmentsOfOtherCompanies(): void
    {
        $client = static::createClient();
        [$firstCompany, $firstToken] = $this->registerAndLogin($client);
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        [$secondCompany] = $this->registerAndLogin($client, [
            'id'           => 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            'socialReason' => 'Segunda Empresa SRL',
            'cuit'         => '20987654321',
            'email'        => 'segunda@empresa.com',
        ]);
        $otherCredit = $this->creditRequest('20000000-0000-4000-8000-000000000000', $secondCompany['id'], CreditRequestStatus::Active);
        $this->installment($otherCredit, 1, InstallmentStatus::Overdue, $monthStart->modify('-1 month'), '1000.00');
        $this->installment($otherCredit, 2, InstallmentStatus::Pending, $monthStart, '1000.00');

        $ownCredit = $this->creditRequest('10000000-0000-4000-8000-000000000000', $firstCompany['id'], CreditRequestStatus::Active);
        $ownPayment = $this->installment($ownCredit, 1, InstallmentStatus::Pending, $monthStart, '1000.00');
        $this->flush();

        $payments = $this->requestPayments($client, $firstToken);

        self::assertSame([$ownPayment->getId()], $this->ids($payments['monthPayments']));
        self::assertSame([], $this->ids($payments['overduePayments']));
    }

    public function testRequiresThePaymentCalendarViewPermission(): void
    {
        $client = static::createClient();
        [, $adminToken] = $this->registerAndLogin($client);

        $deniedToken = $this->createMemberAndLogin($client, $adminToken, ['total_debt.view']);
        $client->jsonRequest('GET', self::URL, [], self::bearer($deniedToken));
        self::assertResponseStatusCodeSame(403);

        $allowedToken = $this->createMemberAndLogin($client, $adminToken, ['payment_calendar.view'], [
            'id'            => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'taxIdentifier' => '27666777888',
        ]);
        $client->jsonRequest('GET', self::URL, [], self::bearer($allowedToken));
        self::assertResponseIsSuccessful();
    }

    public function testRejectsUnauthenticatedRequests(): void
    {
        $client = static::createClient();

        $client->jsonRequest('GET', self::URL);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayments(KernelBrowser $client, string $token): array
    {
        $client->jsonRequest('GET', self::URL, [], self::bearer($token));

        self::assertResponseIsSuccessful();

        return json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array{installments: list<array<string, mixed>>} $paymentList
     *
     * @return string[]
     */
    private function ids(array $paymentList): array
    {
        return array_column($paymentList['installments'], 'id');
    }

    private function creditRequest(string $id, string $companyId, CreditRequestStatus $status): CreditRequest
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $creditRequest = new CreditRequest($id, 'Crédito ' . $id, '100000', '55', 12, $em->find(Company::class, $companyId), $status);
        $em->persist($creditRequest);

        return $creditRequest;
    }

    private function installment(
        CreditRequest $creditRequest,
        int $periodNumber,
        InstallmentStatus $status,
        \DateTimeImmutable $dueDate,
        string $totalAmount,
    ): Installment {
        $installment = new Installment(
            substr($creditRequest->getId(), 0, -12) . sprintf('%012d', $periodNumber),
            $periodNumber,
            $totalAmount,
            '0.00',
            '0.00',
            $totalAmount,
            $status,
            $creditRequest,
        );
        $installment->changeDueDate($dueDate);
        self::getContainer()->get(EntityManagerInterface::class)->persist($installment);

        return $installment;
    }

    private function flush(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
