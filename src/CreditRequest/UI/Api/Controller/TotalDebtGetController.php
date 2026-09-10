<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\DTO\TotalDebtSearchDto;
use App\CreditRequest\Application\UseCase\TotalDebtCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TotalDebtGetController
{
    public function __construct(
        private readonly TotalDebtCalculator $totalDebtCalculator,
        private readonly ValidatorInterface  $validator,
    ) {}

    #[Route('/company-total-debt', name: 'company_total_debt', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $input = new TotalDebtSearchDto($request, $this->validator);

        $totalAmount = $this->totalDebtCalculator->execute($input);

        return new JsonResponse(['totalAmount' => $totalAmount], Response::HTTP_OK);
    }
}
