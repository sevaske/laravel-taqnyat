<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Fixtures;

use Illuminate\Notifications\Notifiable;

final class User
{
    use Notifiable;

    /**
     * @param string|list<string>|null $phone
     */
    public function __construct(
        public string|array|null $phone,
    ) {}

    /**
     * @return string|list<string>|null
     */
    public function routeNotificationForTaqnyat(): string|array|null
    {
        return $this->phone;
    }

    public function getKey(): int
    {
        return 1;
    }
}
