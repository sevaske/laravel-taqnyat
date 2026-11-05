<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Events;

use Sevaske\Taqnyat\Message\SmsMessage;

/**
 * Dispatched right before a message is sent.
 */
final class MessageSending
{
    public function __construct(
        public readonly SmsMessage $message,
    ) {}
}
