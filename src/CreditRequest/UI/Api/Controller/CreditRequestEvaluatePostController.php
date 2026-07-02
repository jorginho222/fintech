<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\CreditRequestEvaluateDto;
use App\CreditRequest\Application\UseCase\CreditRequestEvaluator;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Model\Installment;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CreditRequestEvaluatePostController
{
    public function __construct(
        private readonly CreditRequestEvaluator $creditRequestEvaluator,
        private readonly ValidatorInterface     $validator,
    )
    {
    }

    #[Route('/api/credit-request/evaluate', name: 'credit_request_evaluate', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new CreditRequestEvaluateDto($request, $this->validator);
        $creditRequest = $this->creditRequestEvaluator->execute($input);

        return new JsonResponse($this->serializeCreditRequest($creditRequest), Response::HTTP_OK);
    }

    private function serializeCreditRequest(CreditRequest $creditRequest): array
    {
        return [
            'id' => $creditRequest->getId(),
            'status' => $creditRequest->getStatus()->value,
            'totalAmount' => $creditRequest->getTotalAmount(),
            'nominalInterestRate' => $creditRequest->getNominalInterestRate(),
            'installmentQuantity' => $creditRequest->getInstallmentQuantity(),
            'createdAt' => $creditRequest->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'company' => [
                'id' => $creditRequest->getCompany()->getId(),
                'socialReason' => $creditRequest->getCompany()->getSocialReason(),
                'cuit' => $creditRequest->getCompany()->getCuit(),
                'email' => $creditRequest->getCompany()->getEmail(),
                'taxStatus' => $creditRequest->getCompany()->getTaxStatus()->value,
            ],
            'installments' => array_map(
                static fn (Installment $installment): array => [
                    'id' => $installment->getId(),
                    'periodNumber' => $installment->getPeriodNumber(),
                    'capitalAmount' => $installment->getCapitalAmount(),
                    'interestAmount' => $installment->getInterestAmount(),
                    'taxOnInterestAmount' => $installment->getTaxOnInterestAmount(),
                    'totalAmount' => $installment->getTotalAmount(),
                    'dueDate' => $installment->getDueDate()->format(\DateTimeInterface::ATOM),
                    'status' => $installment->getStatus()->value,
                ],
                $creditRequest->getInstallmentCollection()->toArray(),
            ),
        ];
    }
}
