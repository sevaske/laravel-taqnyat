<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Fixtures;

use Illuminate\Notifications\Notification;

/**
 * Sends whatever the given factory returns from toTaqnyat().
 */
final class SmsNotification extends Notification
{
    /**
     * @var callable(mixed): mixed
     */
    private $factory;

    private string $channel;

    /**
     * @param callable(mixed): mixed $factory
     */
    public function __construct(callable $factory, string $channel = 'taqnyat')
    {
        $this->factory = $factory;
        $this->channel = $channel;
    }

    /**
     * @param mixed $notifiable
     *
     * @return list<string>
     */
    public function via($notifiable): array
    {
        return [$this->channel];
    }

    /**
     * @param mixed $notifiable
     *
     * @return mixed
     */
    public function toTaqnyat($notifiable)
    {
        return ($this->factory)($notifiable);
    }
}
