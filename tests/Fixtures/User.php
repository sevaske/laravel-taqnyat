<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Fixtures;

use Illuminate\Notifications\Notifiable;

final class User
{
    use Notifiable;

    /**
     * @var string|list<string>|null
     */
    public $phone;

    /**
     * @param string|list<string>|null $phone
     */
    public function __construct($phone)
    {
        $this->phone = $phone;
    }

    /**
     * @return string|list<string>|null
     */
    public function routeNotificationForTaqnyat()
    {
        return $this->phone;
    }

    public function getKey(): int
    {
        return 1;
    }
}
