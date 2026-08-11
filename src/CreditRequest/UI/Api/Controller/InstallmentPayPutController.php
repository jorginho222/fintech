<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\CreditRequest\Application\UseCase\InstallmentToPaidUpdater;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use App\Shared\Domain\Service\TransactionManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InstallmentPayPutController
{
    public function __construct(
        private readonly InstallmentToPaidUpdater    $installmentToPaidUpdater,
        private readonly CreditRequestSerializer     $creditRequestSerializer,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    #[Route('/installment/{id}/pay', name: 'installment_pay', methods: ['PUT'])]
    public function __invoke(string $id): JsonResponse
    {
        $creditRequest = $this->transactionManager->transactional(
            fn () => $this->installmentToPaidUpdater->execute($id),
        );

        return new JsonResponse($this->creditRequestSerializer->serialize($creditRequest), Response::HTTP_OK);
    }
}
