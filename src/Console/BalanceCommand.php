<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Console;

use Sevaske\LaravelTaqnyat\Taqnyat;

final class BalanceCommand extends TaqnyatCommand
{
    /**
     * @var string
     */
    protected $signature = 'taqnyat:balance';

    /**
     * @var string
     */
    protected $description = 'Show the Taqnyat account balance';

    protected function handleWith(Taqnyat $taqnyat): int
    {
        $balance = $taqnyat->balance();

        $this->table(['Field', 'Value'], [
            ['Balance', $balance->getBalance() . ' ' . $balance->getCurrency()],
            ['Points', $balance->getPoints()],
            ['Account status', $balance->getAccountStatus()],
            ['Expiry date', $balance->getAccountExpiryDate()],
        ]);

        return self::SUCCESS;
    }
}
