<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Notifications;

use Illuminate\Notifications\Notification;
use Sevaske\LaravelTaqnyat\Taqnyat;
use Sevaske\Taqnyat\Exception\InvalidArgumentException;
use Sevaske\Taqnyat\Exception\TaqnyatException;
use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

/**
 * Sends notifications as SMS through Taqnyat.
 *
 * In the notification:
 *  - return 'taqnyat' or TaqnyatChannel::class from via();
 *  - implement toTaqnyat($notifiable) returning a string, a TaqnyatMessage or an SmsMessage.
 *
 * In the notifiable, return the phone number(s) from routeNotificationForTaqnyat(),
 * or use Notification::route('taqnyat', '966500000000') for on-demand notifications.
 */
final class TaqnyatChannel
{
    public const NAME = 'taqnyat';

    private Taqnyat $taqnyat;

    public function __construct(Taqnyat $taqnyat)
    {
        $this->taqnyat = $taqnyat;
    }

    /**
     * @param mixed $notifiable
     *
     * @return SendSmsResponse|null null when the notifiable has no phone number
     *
     * @throws TaqnyatException
     */
    public function send($notifiable, Notification $notification): ?SendSmsResponse
    {
        $message = $this->getMessage($notifiable, $notification);

        if ($message instanceof SmsMessage) {
            return $this->taqnyat->send($message);
        }

        $recipients = $message->getRecipients() ?? $this->getRoute($notifiable, $notification);

        if (self::isEmptyRoute($recipients)) {
            return null;
        }

        $recipients = self::toRecipientList($recipients, $notification);

        $smsMessage = $this->buildSmsMessage($message, $recipients);

        return $this->taqnyat->send($smsMessage);
    }

    /**
     * @param mixed $notifiable
     *
     * @return TaqnyatMessage|SmsMessage
     */
    private function getMessage($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toTaqnyat')) {
            $message = sprintf('Notification %s must define a toTaqnyat($notifiable) method.', get_class($notification));

            throw new InvalidArgumentException($message);
        }

        $message = $notification->toTaqnyat($notifiable);

        if (is_string($message)) {
            return TaqnyatMessage::create($message);
        }

        if ($message instanceof TaqnyatMessage || $message instanceof SmsMessage) {
            return $message;
        }

        $errorMessage = sprintf(
            '%s::toTaqnyat() must return a string, %s or %s.',
            get_class($notification),
            TaqnyatMessage::class,
            SmsMessage::class,
        );

        throw new InvalidArgumentException($errorMessage);
    }

    /**
     * Phone number(s) of the notifiable. On-demand notifications may be routed
     * either by the channel name ('taqnyat') or by the channel class.
     *
     * @param mixed $notifiable
     *
     * @return mixed
     */
    private function getRoute($notifiable, Notification $notification)
    {
        if (!is_object($notifiable) || !method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        foreach ([self::NAME, self::class] as $channel) {
            $route = $notifiable->routeNotificationFor($channel, $notification);

            if (!self::isEmptyRoute($route)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @param mixed $route
     */
    private static function isEmptyRoute($route): bool
    {
        return $route === null || $route === '' || $route === [];
    }

    /**
     * Accepts a single phone number or a list of them; each one is validated later by SmsMessage.
     *
     * @param mixed $recipients
     *
     * @return list<string|int|PhoneNumber>
     */
    private static function toRecipientList($recipients, Notification $notification): array
    {
        if (!is_iterable($recipients)) {
            $recipients = [$recipients];
        }

        $list = [];

        foreach ($recipients as $recipient) {
            $isPhoneNumber = is_string($recipient) || is_int($recipient) || $recipient instanceof PhoneNumber;

            if (!$isPhoneNumber) {
                $message = sprintf(
                    'Recipients of %s must be phone numbers (string, int or %s).',
                    get_class($notification),
                    PhoneNumber::class,
                );

                throw new InvalidArgumentException($message);
            }

            $list[] = $recipient;
        }

        return $list;
    }

    /**
     * @param list<string|int|PhoneNumber> $recipients
     */
    private function buildSmsMessage(TaqnyatMessage $message, array $recipients): SmsMessage
    {
        $senderName = $this->taqnyat->resolveSenderName($message->getSender());
        $body = $this->renderBody($message);

        $smsMessage = SmsMessage::create($senderName, $recipients, $body);

        $scheduledAt = $message->getScheduledAt();

        if ($scheduledAt !== null) {
            $smsMessage = $smsMessage->scheduleAt($scheduledAt);
        }

        $deleteId = $message->getDeleteId();

        if ($deleteId !== null) {
            $smsMessage = $smsMessage->withDeleteId($deleteId);
        }

        return $smsMessage;
    }

    private function renderBody(TaqnyatMessage $message): string
    {
        if (!$message->hasTemplate()) {
            return $message->getContent();
        }

        $template = $this->taqnyat->template($message->getTemplate());

        return $template->render($message->getTemplateParameters());
    }
}
