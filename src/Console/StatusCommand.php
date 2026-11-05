<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Console;

use Sevaske\LaravelTaqnyat\Taqnyat;

final class StatusCommand extends TaqnyatCommand
{
    /**
     * @var string
     */
    protected $signature = 'taqnyat:status';

    /**
     * @var string
     */
    protected $description = 'Show the Taqnyat SMS service status';

    protected function handleWith(Taqnyat $taqnyat): int
    {
        $status = $taqnyat->status();

        if ($status->isOperational()) {
            $this->info('Taqnyat SMS service is operational: ' . $status->getDescription());

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Taqnyat SMS service is under maintenance: %s (%s)',
            $status->getDescription(),
            $status->getService(),
        ));

        return self::FAILURE;
    }
}
