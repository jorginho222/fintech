<?php

declare(strict_types=1);

namespace App\CreditRequest\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The credit bureau calls back with a shared secret instead of a company token:
 * it acts on its own behalf, not on behalf of a logged-in company.
 */
final class CreditBureauWebhookAuthenticator extends AbstractAuthenticator
{
    public const string HEADER = 'X-Webhook-Secret';
    public const string ROLE   = 'ROLE_CREDIT_BUREAU_WEBHOOK';

    private const string USER_IDENTIFIER = 'credit-bureau-webhook';

    public function __construct(private readonly string $webhookSecret) {}

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $secret = $request->headers->get(self::HEADER, '');

        if ($this->webhookSecret === '' || !hash_equals($this->webhookSecret, $secret)) {
            throw new CustomUserMessageAuthenticationException('Invalid webhook secret.');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                self::USER_IDENTIFIER,
                static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null, [self::ROLE]),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Invalid webhook secret.'], Response::HTTP_UNAUTHORIZED);
    }
}
