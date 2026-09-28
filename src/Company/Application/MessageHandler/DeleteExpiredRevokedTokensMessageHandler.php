<?php

declare(strict_types=1);

namespace App\Company\Application\MessageHandler;

use App\Company\Application\Message\DeleteExpiredRevokedTokensMessage;
use App\Company\Application\UseCase\RevokedTokenExpiredDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteExpiredRevokedTokensMessageHandler
{
    public function __construct(
        private readonly RevokedTokenExpiredDeleter $revokedTokenExpiredDeleter,
    ) {}

    public function __invoke(DeleteExpiredRevokedTokensMessage $message): void
    {
        $this->revokedTokenExpiredDeleter->execute(new \DateTimeImmutable());
    }
}
