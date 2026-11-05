<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\LaravelTaqnyat\Tests\TestCase;
use Sevaske\Taqnyat\Message\SmsMessage;

final class CommandsTest extends TestCase
{
    public function testStatusOperational(): void
    {
        $this->mockHttpResponses(self::jsonResponse(200, [
            'statusCode' => 200,
            'status' => ['service' => 'none', 'description' => 'All Services are Operational'],
        ]));

        self::assertSame(0, Artisan::call('taqnyat:status'));
        self::assertStringContainsString('operational: All Services are Operational', Artisan::output());
    }

    public function testStatusMaintenanceFails(): void
    {
        $this->mockHttpResponses(self::jsonResponse(503, [
            'statusCode' => 200,
            'status' => ['service' => 'Rest API (HTTPS)', 'description' => 'Down for Maintenance'],
        ]));

        self::assertSame(1, Artisan::call('taqnyat:status'));
        self::assertStringContainsString('under maintenance: Down for Maintenance', Artisan::output());
    }

    public function testBalance(): void
    {
        $this->mockHttpResponses(self::jsonResponse(200, [
            'statusCode' => 200,
            'accountStatus' => 'active',
            'accountExpiryDate' => '15-03-2031',
            'balance' => '100.0000',
            'currency' => 'SAR',
            'points' => '667',
        ]));

        self::assertSame(0, Artisan::call('taqnyat:balance'));

        $output = Artisan::output();
        self::assertStringContainsString('100.0000 SAR', $output);
        self::assertStringContainsString('15-03-2031', $output);
    }

    public function testSenders(): void
    {
        $this->mockHttpResponses(self::jsonResponse(201, [
            'statusCode' => 201,
            'senders' => [['senderName' => 'MyBrand', 'status' => 'active', 'destination' => 'KSA']],
        ]));

        self::assertSame(0, Artisan::call('taqnyat:senders'));
        self::assertStringContainsString('MyBrand', Artisan::output());
    }

    public function testErrorsAreReportedWithFailingExitCode(): void
    {
        $this->mockHttpResponses(self::jsonResponse(401, ['statusCode' => 401, 'message' => 'invalid credentials information.']));

        self::assertSame(1, Artisan::call('taqnyat:balance'));
        self::assertStringContainsString('invalid credentials information.', Artisan::output());
    }

    public function testSendText(): void
    {
        $this->mockHttpResponses(self::sendSuccessResponse());

        $exitCode = Artisan::call('taqnyat:send', [
            'recipients' => ['966500000000'],
            '--message' => 'Hello from artisan',
            '--sender' => 'MyBrand',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Message sent. ID: 5829452722', Artisan::output());
        self::assertSame('Hello from artisan', $this->lastRequestPayload()['body']);
        self::assertSame('MyBrand', $this->lastRequestPayload()['sender']);
    }

    public function testSendTemplateWithParameters(): void
    {
        $sms = Taqnyat::fake();

        $exitCode = Artisan::call('taqnyat:send', [
            'recipients' => ['966500000000', '966511111111'],
            '--template' => 'otp',
            '--param' => ['code=4821'],
        ]);

        self::assertSame(0, $exitCode);
        $sms->assertSentTo('966511111111', static function (SmsMessage $message): bool {
            return $message->getBody() === 'Your code is 4821';
        });
    }

    public function testSendRequiresExactlyOneOfMessageOrTemplate(): void
    {
        $sms = Taqnyat::fake();

        self::assertSame(1, Artisan::call('taqnyat:send', ['recipients' => ['966500000000']]));
        self::assertStringContainsString('Pass either --message or --template.', Artisan::output());

        $sms->assertNothingSent();
    }
}
