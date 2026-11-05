<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Console;

use Sevaske\LaravelTaqnyat\Taqnyat;

final class SendersCommand extends TaqnyatCommand
{
    /**
     * @var string
     */
    protected $signature = 'taqnyat:senders';

    /**
     * @var string
     */
    protected $description = 'List sender names registered in your Taqnyat account';

    protected function handleWith(Taqnyat $taqnyat): int
    {
        $senders = $taqnyat->senders();

        if (count($senders) === 0) {
            $this->warn('No sender names found in your Taqnyat account.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($senders as $sender) {
            $rows[] = [$sender->getName(), $sender->getStatus(), $sender->getDestination()];
        }

        $this->table(['Name', 'Status', 'Destination'], $rows);

        return self::SUCCESS;
    }
}
