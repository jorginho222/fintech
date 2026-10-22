<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use App\CreditRequest\Application\DTO\InstallmentPendingToPaySearchDto;
use App\CreditRequest\Application\UseCase\InstallmentPendingToPaySearcher;
use App\CreditRequest\Domain\Model\Installment;
use App\CreditRequest\UI\Api\Serializer\InstallmentSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstallmentPendingToPaySearchGetController
{
    public function __construct(
        private readonly InstallmentPendingToPaySearcher $installmentPendingToPaySearcher,
        private readonly InstallmentSerializer            $installmentSerializer,
        private readonly ValidatorInterface               $validator,
    ) {}

    #[IsGranted(Permission::PaymentCalendarView->value)]
    #[Route('/installment/pending-to-pay/search', name: 'installment_pending_to_pay_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new InstallmentPendingToPaySearchDto($request, $this->validator);

        $installments = $this->installmentPendingToPaySearcher->execute($input);

        $totalAmount = array_reduce(
            $installments,
            static fn (string $totalAmount, Installment $installment): string => bcadd($totalAmount, $installment->getTotalAmount(), 2),
            '0.00',
        );

        return new JsonResponse(
            [
                'totalAmount' => $totalAmount,
                'installments' => array_map(
                    fn (Installment $installment): array => $this->installmentSerializer->serialize($installment),
                    $installments,
                ),
            ],
            Response::HTTP_OK,
        );
    }
}
