<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * A refresh token must go through POST /refresh, never straight to the API as a bearer
 * credential. Registered on lexik_jwt_authentication.on_jwt_authenticated, which only fires
 * for tokens that authenticated a firewall request (not for our own manual parse() calls).
 */
final class RefreshTokenAuthenticationGuard
{
    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        if (($event->getPayload()['type'] ?? null) === 'refresh') {
            throw new AuthenticationException('A refresh token cannot be used to authenticate requests.');
        }
    }
}
