<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Exceptions;

use Sevaske\Taqnyat\Exception\ConfigurationException as BaseConfigurationException;

/**
 * Thrown when the package configuration is incomplete.
 *
 * Extends the core exception, so it is also an instance of {@see \Sevaske\Taqnyat\Exception\TaqnyatException}.
 */
class ConfigurationException extends BaseConfigurationException
{
    public static function missingToken(): self
    {
        return new self('Taqnyat bearer token is not configured. Set TAQNYAT_TOKEN in your .env file.');
    }

    public static function missingSender(): self
    {
        return new self(
            'No sender name given and no default sender is configured. '
            . 'Pass a sender explicitly or set TAQNYAT_SENDER in your .env file.',
        );
    }

    public static function invalidValue(string $key, string $expected): self
    {
        return new self(sprintf('Config value "taqnyat.%s" must be %s.', $key, $expected));
    }
}
