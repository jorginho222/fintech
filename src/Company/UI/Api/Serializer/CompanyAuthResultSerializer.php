<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Serializer;

use App\Company\Application\DTO\CompanyLoginResultDto;
use App\Company\Domain\Service\AuthToken;

final class CompanyAuthResultSerializer
{
    public function serialize(CompanyLoginResultDto $result): array
    {
        return [
            'accessToken' => $this->serializeToken($result->accessToken),
            'refreshToken' => $this->serializeToken($result->refreshToken),
            'company' => [
                'id' => $result->company->getId(),
                'socialReason' => $result->company->getSocialReason(),
                'cuit' => $result->company->getCuit(),
                'email' => $result->company->getEmail(),
                'taxStatus' => $result->company->getTaxStatus()->value,
            ],
        ];
    }

    private function serializeToken(AuthToken $token): array
    {
        return [
            'token' => $token->value,
            'expiresAt' => $token->expiresAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
