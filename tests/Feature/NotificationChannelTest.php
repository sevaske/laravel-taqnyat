<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Feature;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\LaravelTaqnyat\Notifications\TaqnyatChannel;
use Sevaske\LaravelTaqnyat\Notifications\TaqnyatMessage;
use Sevaske\LaravelTaqnyat\Testing\FakeSmsSender;
use Sevaske\LaravelTaqnyat\Tests\Fixtures\SmsNotification;
use Sevaske\LaravelTaqnyat\Tests\Fixtures\User;
use Sevaske\LaravelTaqnyat\Tests\TestCase;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Message\SmsMessage;

final class NotificationChannelTest extends TestCase
{
    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = Taqnyat::fake();
    }

    public function testSendsStringMessageToNotifiableRoute(): void
    {
        (new User('+966500000000'))->notify(new SmsNotification(static fn(): string => 'Your order has shipped'));

        $this->sms->assertSentTo('966500000000', static function (SmsMessage $message): bool {
            return $message->getBody() === 'Your order has shipped'
                && $message->getSender() === 'DefaultSender';
        });
    }

    public function testChannelClassCanBeUsedInVia(): void
    {
        (new User('966500000000'))->notify(new SmsNotification(static fn(): string => 'Hello', TaqnyatChannel::class));

        $this->sms->assertSentCount(1);
    }

    public function testSendsTemplatedMessageWithAllOptions(): void
    {
        $user = new User(['966500000000', '966511111111']);

        $user->notify(new SmsNotification(static function (): TaqnyatMessage {
            return TaqnyatMessage::create()
                ->template('otp', ['code' => 4821])
                ->sender('MyBrand')
                ->scheduleAt(new \DateTimeImmutable('2030-01-01 09:00', new \DateTimeZone('Asia/Riyadh')))
                ->deleteId(55);
        }));

        $messages = $this->sms->sent();
        self::assertCount(1, $messages);

        $message = $messages[0];
        self::assertSame('Your code is 4821', $message->getBody());
        self::assertSame('MyBrand', $message->getSender());
        self::assertSame(['966500000000', '966511111111'], $message->getRecipientNumbers());
        self::assertSame(55, $message->getDeleteId());
        self::assertTrue($message->isScheduled());
    }

    public function testMessageRecipientsOverrideTheRoute(): void
    {
        (new User('966500000000'))->notify(new SmsNotification(static function (): TaqnyatMessage {
            return TaqnyatMessage::create('Hi')->to('966599999999');
        }));

        $this->sms->assertSentTo('966599999999');
        $this->sms->assertNotSentTo('966500000000');
    }

    public function testOnDemandNotificationByChannelNameOrClass(): void
    {
        $notification = new SmsNotification(static fn(): string => 'On demand');

        Notification::route('taqnyat', '966500000000')->notify($notification);
        Notification::route(TaqnyatChannel::class, '966511111111')->notify($notification);

        $this->sms->assertSentTo('966500000000');
        $this->sms->assertSentTo('966511111111');
    }

    public function testSmsMessageIsSentAsIs(): void
    {
        (new User(null))->notify(new SmsNotification(static function (): SmsMessage {
            return SmsMessage::create('Custom', '966577777777', 'Full control');
        }));

        $this->sms->assertSentTo('966577777777', static function (SmsMessage $message): bool {
            return $message->getSender() === 'Custom';
        });
    }

    public function testNotifiableWithoutPhoneNumberIsSkipped(): void
    {
        (new User(null))->notify(new SmsNotification(static fn(): string => 'Nobody receives this'));

        $this->sms->assertNothingSent();
    }

    public function testInvalidReturnValueIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('toTaqnyat() must return a string');

        (new User('966500000000'))->notify(new SmsNotification(static fn(): array => ['not a message']));
    }

    public function testNotificationWithoutToTaqnyatIsReported(): void
    {
        $notification = new class extends BaseNotification {
            /**
             * @return list<string>
             */
            public function via(): array
            {
                return ['taqnyat'];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must define a toTaqnyat($notifiable) method');

        (new User('966500000000'))->notify($notification);
    }
}
