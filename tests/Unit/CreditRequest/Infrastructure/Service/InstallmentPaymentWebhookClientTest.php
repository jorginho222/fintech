<?php

declare(strict_types=1);

namespace App\Tests\Unit\CreditRequest\Infrastructure\Service;

use App\CreditRequest\Infrastructure\Service\InstallmentPaymentWebhookClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InstallmentPaymentWebhookClientTest extends TestCase
{
    public function testPostsThePaymentWithTheInstallmentIdAsIdempotencyKey(): void
    {
        $response = new MockResponse();
        $httpClient = new MockHttpClient($response);

        (new InstallmentPaymentWebhookClient($httpClient, 'https://payment-gateway.test/webhook'))
            ->sendRequest('cccccccc-cccc-4ccc-8ccc-000000000003', '20123456780', '893833.33');

        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://payment-gateway.test/webhook', $response->getRequestUrl());
        self::assertContains('Idempotency-Key: cccccccc-cccc-4ccc-8ccc-000000000003', $response->getRequestOptions()['headers']);
        self::assertSame(
            ['installmentId' => 'cccccccc-cccc-4ccc-8ccc-000000000003', 'cuit' => '20123456780', 'amount' => '893833.33'],
            json_decode($response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
