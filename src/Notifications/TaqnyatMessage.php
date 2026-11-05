<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Notifications;

use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Template\Template;

/**
 * Fluent SMS message returned from a notification's toTaqnyat() method.
 *
 *     public function toTaqnyat($notifiable): TaqnyatMessage
 *     {
 *         return TaqnyatMessage::create()
 *             ->template('otp', ['code' => $this->code])
 *             ->sender('MyBrand');
 *     }
 *
 * Anything not set falls back to defaults: the sender to config("taqnyat.sender"),
 * recipients to the notifiable's route (routeNotificationForTaqnyat()).
 */
final class TaqnyatMessage
{
    private string $content;

    /**
     * @var Template|string|null
     */
    private $template = null;

    /**
     * @var array<string, mixed>
     */
    private array $templateParameters = [];

    private ?string $sender = null;

    /**
     * @var iterable<string|int|PhoneNumber>|string|int|PhoneNumber|null
     */
    private $recipients = null;

    private ?\DateTimeInterface $scheduledAt = null;

    private ?int $deleteId = null;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public static function create(string $content = ''): self
    {
        return new self($content);
    }

    /**
     * Sets the message text. Ignored when a template is set.
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Uses a template instead of plain content.
     *
     * @param Template|string      $template   template name from config("taqnyat.templates") or a Template instance
     * @param array<string, mixed> $parameters placeholder values
     */
    public function template($template, array $parameters = []): self
    {
        $this->template = $template;
        $this->templateParameters = $parameters;

        return $this;
    }

    /**
     * Overrides the default sender name.
     */
    public function sender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Overrides the recipients taken from the notifiable.
     *
     * @param iterable<string|int|PhoneNumber>|string|int|PhoneNumber $recipients
     */
    public function to($recipients): self
    {
        $this->recipients = $recipients;

        return $this;
    }

    /**
     * Schedules the message to be sent later.
     */
    public function scheduleAt(\DateTimeInterface $dateTime): self
    {
        $this->scheduledAt = $dateTime;

        return $this;
    }

    /**
     * Sets your own identifier to delete the scheduled message later.
     */
    public function deleteId(int $deleteId): self
    {
        $this->deleteId = $deleteId;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return Template|string|null
     */
    public function getTemplate()
    {
        return $this->template;
    }

    public function hasTemplate(): bool
    {
        return $this->template !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateParameters(): array
    {
        return $this->templateParameters;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    /**
     * @return iterable<string|int|PhoneNumber>|string|int|PhoneNumber|null
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function getDeleteId(): ?int
    {
        return $this->deleteId;
    }
}
