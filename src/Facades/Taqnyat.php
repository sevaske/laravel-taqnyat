<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Facades;

use Illuminate\Support\Facades\Facade;
use Sevaske\LaravelTaqnyat\Taqnyat as TaqnyatService;
use Sevaske\LaravelTaqnyat\Testing\FakeSmsSender;
use Sevaske\Taqnyat\Contracts\SmsSender;

/**
 * @method static \Sevaske\Taqnyat\Response\SendSmsResponse send(\Sevaske\Taqnyat\Message\SmsMessage $message)
 * @method static \Sevaske\Taqnyat\Response\SendSmsResponse sendText(iterable<string|int|\Sevaske\Taqnyat\Message\PhoneNumber>|string|int|\Sevaske\Taqnyat\Message\PhoneNumber $recipients, string $body, ?string $sender = null)
 * @method static \Sevaske\Taqnyat\Response\SendSmsResponse sendTemplate(iterable<string|int|\Sevaske\Taqnyat\Message\PhoneNumber>|string|int|\Sevaske\Taqnyat\Message\PhoneNumber $recipients, \Sevaske\Taqnyat\Template\Template|string $template, array<string, mixed> $parameters = [], ?string $sender = null)
 * @method static \Sevaske\Taqnyat\Message\SmsMessage message(iterable<string|int|\Sevaske\Taqnyat\Message\PhoneNumber>|string|int|\Sevaske\Taqnyat\Message\PhoneNumber $recipients, string $body, ?string $sender = null)
 * @method static \Sevaske\Taqnyat\Response\DeleteMessageResponse delete(int $deleteId)
 * @method static \Sevaske\Taqnyat\Response\SendersResponse senders()
 * @method static \Sevaske\Taqnyat\Response\BalanceResponse balance()
 * @method static \Sevaske\Taqnyat\Response\SystemStatusResponse status()
 * @method static \Sevaske\Taqnyat\Template\Template template(\Sevaske\Taqnyat\Template\Template|string $template)
 * @method static \Sevaske\Taqnyat\Template\TemplateRegistry templates()
 * @method static string resolveSenderName(?string $senderName = null)
 * @method static string|null getDefaultSender()
 * @method static \Sevaske\Taqnyat\Client client()
 *
 * @see TaqnyatService
 */
final class Taqnyat extends Facade
{
    /**
     * Replaces real sending with a fake that records messages, and returns it for assertions.
     *
     * Covers the facade, the Taqnyat service, the notification channel and anything resolving
     * the SmsSender contract from the container. Other endpoints (balance, senders...) are not faked.
     */
    public static function fake(): FakeSmsSender
    {
        $fake = new FakeSmsSender();

        $app = static::getFacadeApplication();

        if ($app === null) {
            throw new \RuntimeException('Taqnyat::fake() requires a booted Laravel application.');
        }

        // The Taqnyat service resolves SmsSender on every send, so rebinding it is enough.
        $app->instance(SmsSender::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return TaqnyatService::class;
    }
}
