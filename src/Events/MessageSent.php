<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Events;

use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

/**
 * Dispatched after Taqnyat has accepted a message.
 */
final class MessageSent
{
    public function __construct(
        public readonly SmsMessage $message,
        public readonly SendSmsResponse $response,
    ) {}
}
