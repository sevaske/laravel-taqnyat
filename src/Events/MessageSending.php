<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Events;

use Sevaske\Taqnyat\Message\SmsMessage;

/**
 * Dispatched right before a message is sent.
 */
final class MessageSending
{
    public SmsMessage $message;

    public function __construct(SmsMessage $message)
    {
        $this->message = $message;
    }
}
