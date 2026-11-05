<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Console;

use Illuminate\Console\Command;
use Sevaske\LaravelTaqnyat\Taqnyat;
use Sevaske\Taqnyat\Exception\TaqnyatException;

/**
 * Base class for Taqnyat commands: turns library errors into a readable message and a failing exit code.
 */
abstract class TaqnyatCommand extends Command
{
    public function handle(Taqnyat $taqnyat): int
    {
        try {
            return $this->handleWith($taqnyat);
        } catch (TaqnyatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @throws TaqnyatException
     */
    abstract protected function handleWith(Taqnyat $taqnyat): int;
}
