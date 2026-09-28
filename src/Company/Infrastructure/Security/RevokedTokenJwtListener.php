<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;

/**
 * Rejects tokens revoked via /logout. Registered on lexik_jwt_authentication.on_jwt_decoded.
 */
final class RevokedTokenJwtListener
{
    public function __construct(private readonly RevokedTokenRepositoryInterface $revokedTokenRepository) {}

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $jti = $event->getPayload()['jti'] ?? null;

        if (is_string($jti) && $this->revokedTokenRepository->existsByJti($jti)) {
            $event->markAsInvalid();
        }
    }
}
