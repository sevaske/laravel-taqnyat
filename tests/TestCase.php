<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase as Orchestra;
use Psr\Http\Message\RequestInterface;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\LaravelTaqnyat\TaqnyatServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Requests captured by Guzzle's history middleware.
     *
     * @var list<array{request: RequestInterface}>
     */
    protected array $history = [];

    protected function getPackageProviders($app): array
    {
        return [TaqnyatServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Taqnyat' => Taqnyat::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('taqnyat.token', 'test-token');
        $app['config']->set('taqnyat.sender', 'DefaultSender');
        $app['config']->set('taqnyat.templates', [
            'otp' => 'Your code is {code}',
        ]);
    }

    /**
     * Makes the Taqnyat client answer with the given responses, in order.
     */
    protected function mockHttpResponses(Response ...$responses): void
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));

        $this->app->instance(TaqnyatServiceProvider::HTTP_CLIENT, new GuzzleClient(['handler' => $handler]));
    }

    /**
     * @param array<string, mixed> $body
     */
    protected static function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected static function sendSuccessResponse(): Response
    {
        return self::jsonResponse(201, [
            'statusCode' => 201,
            'messageId' => '5829452722',
            'cost' => '0.1500',
            'currency' => 'SAR',
            'totalCount' => 1,
            'msgLength' => 1,
            'accepted' => '[966500000000,]',
            'rejected' => '[]',
        ]);
    }

    protected function lastRequest(): RequestInterface
    {
        self::assertNotEmpty($this->history, 'No HTTP request was sent.');

        return $this->history[count($this->history) - 1]['request'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastRequestPayload(): array
    {
        $payload = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }
}
