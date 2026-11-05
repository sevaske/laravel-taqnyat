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
    public SmsMessage $message;

    public SendSmsResponse $response;

    public function __construct(SmsMessage $message, SendSmsResponse $response)
    {
        $this->message = $message;
        $this->response = $response;
    }
}
