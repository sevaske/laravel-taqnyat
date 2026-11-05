<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Sevaske\LaravelTaqnyat\Events\MessageSending;
use Sevaske\LaravelTaqnyat\Events\MessageSent;
use Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\LaravelTaqnyat\Tests\TestCase;
use Sevaske\Taqnyat\Exception\AuthenticationException;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Template\Template;

final class TaqnyatTest extends TestCase
{
    public function testSendTextUsesDefaultSender(): void
    {
        $this->mockHttpResponses(self::sendSuccessResponse());

        $response = Taqnyat::sendText('+966 50 000 0000', 'Hello');

        self::assertSame('5829452722', $response->getMessageId());
        self::assertSame('https://api.taqnyat.sa/v1/messages', (string) $this->lastRequest()->getUri());
        self::assertSame('Bearer test-token', $this->lastRequest()->getHeaderLine('Authorization'));
        self::assertSame([
            'recipients' => ['966500000000'],
            'body' => 'Hello',
            'sender' => 'DefaultSender',
        ], $this->lastRequestPayload());
    }

    public function testSendTextWithExplicitSender(): void
    {
        $this->mockHttpResponses(self::sendSuccessResponse());

        Taqnyat::sendText(['966500000000', '966511111111'], 'Hello', 'OtherSender');

        $payload = $this->lastRequestPayload();
        self::assertSame('OtherSender', $payload['sender']);
        self::assertSame(['966500000000', '966511111111'], $payload['recipients']);
    }

    public function testMissingSenderIsReported(): void
    {
        $this->app['config']->set('taqnyat.sender', null);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('TAQNYAT_SENDER');

        Taqnyat::sendText('966500000000', 'Hello');
    }

    public function testSendTemplateByName(): void
    {
        $this->mockHttpResponses(self::sendSuccessResponse(), self::sendSuccessResponse());

        Taqnyat::sendTemplate('966500000000', 'otp', ['code' => 4821]);
        self::assertSame('Your code is 4821', $this->lastRequestPayload()['body']);

        Taqnyat::sendTemplate('966500000000', new Template('Hi {name}'), ['name' => 'Sara']);
        self::assertSame('Hi Sara', $this->lastRequestPayload()['body']);
    }

    public function testUnknownTemplateIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Template "missing" is not registered.');

        Taqnyat::sendTemplate('966500000000', 'missing');
    }

    public function testScheduledMessageBuiltWithDefaultSender(): void
    {
        $this->mockHttpResponses(self::sendSuccessResponse());

        $message = Taqnyat::message('966500000000', 'Reminder')
            ->scheduleAt(new \DateTimeImmutable('2030-01-01 09:00', new \DateTimeZone('Asia/Riyadh')))
            ->withDeleteId(77);

        Taqnyat::send($message);

        $payload = $this->lastRequestPayload();
        self::assertSame('DefaultSender', $payload['sender']);
        self::assertSame('2030-01-01T09:00', $payload['scheduledDatetime']);
        self::assertSame(77, $payload['deleteId']);
    }

    public function testDispatchesEvents(): void
    {
        Event::fake([MessageSending::class, MessageSent::class]);
        $this->mockHttpResponses(self::sendSuccessResponse());

        Taqnyat::sendText('966500000000', 'Hello');

        Event::assertDispatched(MessageSending::class, static function (MessageSending $event): bool {
            return $event->message->getBody() === 'Hello';
        });
        Event::assertDispatched(MessageSent::class, static function (MessageSent $event): bool {
            return $event->response->getMessageId() === '5829452722';
        });
    }

    public function testApiErrorsArePassedThrough(): void
    {
        $this->mockHttpResponses(self::jsonResponse(401, ['statusCode' => 401, 'message' => 'invalid credentials information.']));

        $this->expectException(AuthenticationException::class);

        Taqnyat::sendText('966500000000', 'Hello');
    }

    public function testOtherEndpoints(): void
    {
        $this->mockHttpResponses(
            self::jsonResponse(200, ['statusCode' => 200, 'balance' => '100.0000', 'currency' => 'SAR', 'points' => '667']),
            self::jsonResponse(201, ['statusCode' => 201, 'senders' => [['senderName' => 'MyBrand', 'status' => 'active', 'destination' => 'KSA']]]),
            self::jsonResponse(200, ['statusCode' => 200, 'status' => ['service' => 'none', 'description' => 'All Services are Operational']]),
            self::jsonResponse(201, ['statusCode' => 201, 'message' => 'Deleted successfully']),
        );

        self::assertSame('100.0000', Taqnyat::balance()->getBalance());
        self::assertSame(['MyBrand'], Taqnyat::senders()->getNames());
        self::assertTrue(Taqnyat::status()->isOperational());
        self::assertSame('Deleted successfully', Taqnyat::delete(77)->getMessage());

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertSame(['deleteId' => 77], $this->lastRequestPayload());
    }
}
