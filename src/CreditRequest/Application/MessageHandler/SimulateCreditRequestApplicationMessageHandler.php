<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\MessageHandler;

use App\CreditRequest\Application\Message\SimulateCreditRequestApplicationMessage;
use App\CreditRequest\Application\UseCase\CreditRequestApplicationSimulationService;
use App\CreditRequest\Domain\Exception\CreditRequestApplicationNotFoundException;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SimulateCreditRequestApplicationMessageHandler
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
        private readonly CreditRequestApplicationSimulationService   $creditRequestApplicationSimulationService,
    ) {}

    public function __invoke(SimulateCreditRequestApplicationMessage $message): void
    {
        $creditRequestApplication = $this->creditRequestApplicationRepository->findById(
            $message->getCreditRequestApplicationId(),
        );
        if ($creditRequestApplication === null) {
            throw new CreditRequestApplicationNotFoundException();
        }

        $this->creditRequestApplicationSimulationService->execute($creditRequestApplication);
    }
}
