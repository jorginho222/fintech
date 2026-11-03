<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Controller;

use App\Company\Domain\Model\Permission;
use App\CreditRequest\Application\UseCase\InstallmentPaymentRequester;
use App\CreditRequest\UI\Api\Serializer\CreditRequestSerializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InstallmentPayRequestPutController
{
    public function __construct(
        private readonly InstallmentPaymentRequester $installmentPaymentRequester,
        private readonly CreditRequestSerializer     $creditRequestSerializer,
    ) {}

    #[IsGranted(Permission::InstallmentPay->value)]
    #[Route('/installment/{id}/pay-request', name: 'installment_pay_request', methods: ['PUT'])]
    public function __invoke(string $id): JsonResponse
    {
        // Not wrapped in a transaction: the worker must be able to read the processing attempt as soon as the message is queued.
        $creditRequest = $this->installmentPaymentRequester->execute($id);

        return new JsonResponse($this->creditRequestSerializer->serialize($creditRequest), Response::HTTP_ACCEPTED);
    }
}
