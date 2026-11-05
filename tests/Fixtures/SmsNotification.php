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
     * @var \Closure(mixed): mixed
     */
    private \Closure $factory;

    /**
     * @param callable(mixed): mixed $factory
     */
    public function __construct(
        callable $factory,
        private readonly string $channel = 'taqnyat',
    ) {
        $this->factory = $factory(...);
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return [$this->channel];
    }

    public function toTaqnyat(mixed $notifiable): mixed
    {
        return ($this->factory)($notifiable);
    }
}
