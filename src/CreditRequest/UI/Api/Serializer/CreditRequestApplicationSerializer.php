<?php

declare(strict_types=1);

namespace App\CreditRequest\UI\Api\Serializer;

use App\CreditRequest\Domain\Model\CreditRequestApplication;

final class CreditRequestApplicationSerializer
{
    public function serialize(CreditRequestApplication $creditRequestApplication): array
    {
        return [
            'id' => $creditRequestApplication->getId(),
            'amount' => $creditRequestApplication->getAmount(),
            'installmentQuantity' => $creditRequestApplication->getInstallmentQuantity(),
            'status' => $creditRequestApplication->getStatus()->value,
            'rejectionReason' => $creditRequestApplication->getRejectionReason(),
            'creditRequestId' => $creditRequestApplication->getCreditRequest()?->getId(),
            'company' => [
                'id' => $creditRequestApplication->getCompany()->getId(),
                'socialReason' => $creditRequestApplication->getCompany()->getSocialReason(),
                'cuit' => $creditRequestApplication->getCompany()->getCuit(),
                'email' => $creditRequestApplication->getCompany()->getEmail(),
                'taxStatus' => $creditRequestApplication->getCompany()->getTaxStatus()->value,
                'score' => $creditRequestApplication->getCompany()->getScore(),
            ],
        ];
    }
}
