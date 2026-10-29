<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use App\CreditRequest\Application\UseCase\InstallmentPaymentsDashboardSearcher;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\UI\Api\Serializer\InstallmentSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InstallmentPaymentsDashboardGetController
{
    public function __construct(
        private readonly InstallmentPaymentsDashboardSearcher $installmentPaymentsDashboardSearcher,
        private readonly InstallmentSerializer                $installmentSerializer,
    ) {}

    #[IsGranted(Permission::PaymentCalendarView->value)]
    #[Route('/installment/dashboard-payments', name: 'installment_dashboard_payments', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $payments = $this->installmentPaymentsDashboardSearcher->execute();

        return new JsonResponse([
            'monthPayments'   => $this->serializeList($payments->monthTotalAmount, $payments->monthPayments),
            'overduePayments' => $this->serializeList($payments->overdueTotalAmount, $payments->overduePayments),
        ], Response::HTTP_OK);
    }

    /**
     * Same shape as the payments calendar response.
     *
     * @param Installment[] $installments
     */
    private function serializeList(string $totalAmount, array $installments): array
    {
        return [
            'totalAmount'  => $totalAmount,
            'installments' => array_map(
                fn (Installment $installment): array => $this->installmentSerializer->serialize($installment),
                $installments,
            ),
        ];
    }
}
