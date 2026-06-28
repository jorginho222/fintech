<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\DomainHttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $e = $event->getThrowable();

        $response = match (true) {
            $e instanceof \JsonException => new JsonResponse(
                ['error' => 'Invalid JSON body.'],
                Response::HTTP_BAD_REQUEST
            ),
            $e instanceof ValidationFailedException => $this->buildValidationResponse($e),
            $e instanceof DomainHttpException => new JsonResponse(
                ['error' => $e->getMessage()],
                $e->httpStatusCode()
            ),
            default => null,
        };

        if ($response !== null) {
            $event->setResponse($response);
        }
    }

    private function buildValidationResponse(ValidationFailedException $e): JsonResponse
    {
        $errors = [];
        foreach ($e->getViolations() as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return new JsonResponse(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
