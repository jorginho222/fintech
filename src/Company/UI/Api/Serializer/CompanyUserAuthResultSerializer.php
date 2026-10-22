<?php

declare(strict_types=1);

namespace App\Company\UI\Api\Serializer;

use App\Company\Application\DTO\CompanyUserLoginResultDto;
use App\Company\Domain\Service\AuthToken;

final class CompanyUserAuthResultSerializer
{
    public function __construct(private readonly CompanyUserSerializer $companyUserSerializer) {}

    public function serialize(CompanyUserLoginResultDto $result): array
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
            'user' => $this->companyUserSerializer->serialize($result->user),
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
