<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class LexikAuthTokenGenerator implements AuthTokenGeneratorInterface
{
    public function __construct(private readonly JWTTokenManagerInterface $jwtTokenManager) {}

    public function generateFor(Company $company): AuthToken
    {
        $user = CompanyUser::fromCompany($company);

        $value = $this->jwtTokenManager->createFromPayload($user, ['companyId' => $company->getId()]);

        return new AuthToken($value, $this->expirationOf($value));
    }

    private function expirationOf(string $token): \DateTimeImmutable
    {
        $payload = $this->jwtTokenManager->parse($token);

        return (new \DateTimeImmutable())->setTimestamp((int) $payload['exp']);
    }
}
