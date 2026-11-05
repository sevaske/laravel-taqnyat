<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Feature;

use PHPUnit\Framework\AssertionFailedError;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\LaravelTaqnyat\Tests\TestCase;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Exception\AuthenticationException;
use Sevaske\Taqnyat\Message\SmsMessage;

final class FakeTest extends TestCase
{
    public function testRecordsMessagesWithoutHttpOrToken(): void
    {
        $this->app['config']->set('taqnyat.token', null);
        $sms = Taqnyat::fake();

        $response = Taqnyat::sendText('+966500000000', 'Your code is 1234');
        Taqnyat::sendTemplate(['966511111111', '966522222222'], 'otp', ['code' => 99]);

        self::assertSame('1', $response->getMessageId());
        self::assertSame(['966500000000'], $response->getAccepted());

        $sms->assertSentCount(2);
        $sms->assertSent();
        $sms->assertSentTo('00966500000000');
        $sms->assertSentTo('966522222222', static function (SmsMessage $message): bool {
            return $message->getBody() === 'Your code is 99';
        });
        $sms->assertNotSentTo('966533333333');
        $sms->assertNotSent(static function (SmsMessage $message): bool {
            return $message->getSender() !== 'DefaultSender';
        });
        self::assertCount(1, $sms->sentTo('966500000000'));
        self::assertCount(2, $sms->sent());
    }

    public function testFakeReplacesTheSmsSenderContract(): void
    {
        $sms = Taqnyat::fake();

        $this->app->make(SmsSender::class)->send(SmsMessage::create('S', '966500000000', 'Direct'));

        $sms->assertSentCount(1);
    }

    public function testAssertNothingSent(): void
    {
        $sms = Taqnyat::fake();

        $sms->assertNothingSent();

        Taqnyat::sendText('966500000000', 'Hello');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected no SMS to be sent, but 1 were sent.');

        $sms->assertNothingSent();
    }

    public function testFailedAssertionsHaveClearMessages(): void
    {
        $sms = Taqnyat::fake();

        try {
            $sms->assertSentTo('966500000000');
            self::fail('Assertion expected to fail');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Expected an SMS to be sent to 966500000000', $e->getMessage());
        }

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected 3 SMS to be sent, but 0 were sent.');

        $sms->assertSentCount(3);
    }

    public function testThrowOnSend(): void
    {
        $sms = Taqnyat::fake()->throwOnSend(new AuthenticationException('Invalid token', 401));

        try {
            Taqnyat::sendText('966500000000', 'Hello');
            self::fail('Exception expected');
        } catch (AuthenticationException $e) {
            $sms->assertNothingSent();
        }
    }
}
