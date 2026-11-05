<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Sevaske\LaravelTaqnyat\Events\MessageSending;
use Sevaske\LaravelTaqnyat\Events\MessageSent;
use Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException;
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\TaqnyatException;
use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\BalanceResponse;
use Sevaske\Taqnyat\Response\DeleteMessageResponse;
use Sevaske\Taqnyat\Response\SendersResponse;
use Sevaske\Taqnyat\Response\SendSmsResponse;
use Sevaske\Taqnyat\Response\SystemStatusResponse;
use Sevaske\Taqnyat\Template\Template;
use Sevaske\Taqnyat\Template\TemplateRegistry;

/**
 * Laravel-friendly entry point to Taqnyat: default sender, templates from config and events.
 *
 * Usually used through the {@see \Sevaske\LaravelTaqnyat\Facades\Taqnyat} facade.
 *
 * Not final only so that the facade can be mocked (Taqnyat::shouldReceive()); not meant to be extended.
 */
class Taqnyat implements SmsSender
{
    /**
     * Both dependencies are resolved lazily, on every use, so that:
     *  - the service can be resolved without a configured token (e.g. when sending is faked in tests);
     *  - swapping the SmsSender binding (Taqnyat::fake()) takes effect immediately.
     *
     * @param Closure(): SmsSender $smsSenderResolver returns what actually sends messages (the client, or a fake)
     * @param Closure(): Client    $clientResolver    returns the API client for the other endpoints
     */
    public function __construct(
        private readonly Closure $smsSenderResolver,
        private readonly Closure $clientResolver,
        private readonly TemplateRegistry $templates,
        private readonly ?string $defaultSender = null,
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * Sends a message and dispatches {@see MessageSending} and {@see MessageSent} events.
     *
     * @throws TaqnyatException
     */
    public function send(SmsMessage $message): SendSmsResponse
    {
        $this->dispatch(new MessageSending($message));

        $response = $this->smsSender()->send($message);

        $this->dispatch(new MessageSent($message, $response));

        return $response;
    }

    /**
     * Sends a plain text message.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     * @param string|null                                             $sender     defaults to config("taqnyat.sender")
     *
     * @throws TaqnyatException
     */
    public function sendText(iterable|string|int|PhoneNumber $recipients, string $body, ?string $sender = null): SendSmsResponse
    {
        $message = $this->message($recipients, $body, $sender);

        return $this->send($message);
    }

    /**
     * Renders a template and sends the result.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     * @param Template|string                                         $template   template name from config("taqnyat.templates") or a Template instance
     * @param array<string, mixed>                                    $parameters
     * @param string|null                                             $sender     defaults to config("taqnyat.sender")
     *
     * @throws TaqnyatException
     */
    public function sendTemplate(
        iterable|string|int|PhoneNumber $recipients,
        Template|string $template,
        array $parameters = [],
        ?string $sender = null,
    ): SendSmsResponse {
        $body = $this->template($template)->render($parameters);
        $message = $this->message($recipients, $body, $sender);

        return $this->send($message);
    }

    /**
     * Builds a message with the default sender, e.g. to schedule it before sending:
     *
     *     Taqnyat::send(Taqnyat::message($phone, 'Reminder')->scheduleAt($date));
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     *
     * @throws TaqnyatException
     */
    public function message(iterable|string|int|PhoneNumber $recipients, string $body, ?string $sender = null): SmsMessage
    {
        $senderName = $this->resolveSenderName($sender);

        return SmsMessage::create($senderName, $recipients, $body);
    }

    /**
     * Deletes a scheduled message by its delete ID.
     *
     * @throws TaqnyatException
     */
    public function delete(int $deleteId): DeleteMessageResponse
    {
        return $this->client()->messages()->delete($deleteId);
    }

    /**
     * @throws TaqnyatException
     */
    public function senders(): SendersResponse
    {
        return $this->client()->messages()->senders();
    }

    /**
     * @throws TaqnyatException
     */
    public function balance(): BalanceResponse
    {
        return $this->client()->account()->balance();
    }

    /**
     * @throws TaqnyatException
     */
    public function status(): SystemStatusResponse
    {
        return $this->client()->system()->status();
    }

    /**
     * Returns a Template instance as is, or looks a template up by name in config("taqnyat.templates").
     *
     * @throws InvalidArgumentException when the template is not registered
     */
    public function template(Template|string $template): Template
    {
        if ($template instanceof Template) {
            return $template;
        }

        return $this->templates->get($template);
    }

    public function templates(): TemplateRegistry
    {
        return $this->templates;
    }

    /**
     * Returns the given sender name, or the default one from config("taqnyat.sender").
     *
     * @throws ConfigurationException when neither is set
     */
    public function resolveSenderName(?string $senderName = null): string
    {
        if ($senderName !== null && trim($senderName) !== '') {
            return $senderName;
        }

        if ($this->defaultSender !== null) {
            return $this->defaultSender;
        }

        throw ConfigurationException::missingSender();
    }

    public function getDefaultSender(): ?string
    {
        return $this->defaultSender;
    }

    /**
     * The underlying framework-agnostic client, for anything not covered here.
     */
    public function client(): Client
    {
        return ($this->clientResolver)();
    }

    private function smsSender(): SmsSender
    {
        return ($this->smsSenderResolver)();
    }

    private function dispatch(object $event): void
    {
        if ($this->events !== null) {
            $this->events->dispatch($event);
        }
    }
}
