<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Exception\PasswordChangeRequiredException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Until a member replaces the admin-set password, only the routes needed to do so (and to
 * leave) are reachable. Registered on kernel.request after the firewall, so the user is known.
 */
final class PasswordChangeRequiredListener
{
    private const array ALLOWED_ROUTES = ['me', 'me_password_change', 'company_user_logout'];

    public function __construct(private readonly Security $security) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof CompanyUser || !$user->mustChangePassword()) {
            return;
        }

        if (!in_array($event->getRequest()->attributes->get('_route'), self::ALLOWED_ROUTES, true)) {
            throw new PasswordChangeRequiredException();
        }
    }
}
