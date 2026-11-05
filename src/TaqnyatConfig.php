<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat;

use Illuminate\Contracts\Config\Repository;
use Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException;

/**
 * Typed access to the "taqnyat" config.
 */
final class TaqnyatConfig
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @throws ConfigurationException when the token is not set
     */
    public function token(): string
    {
        $token = $this->nullableString('token');

        if ($token === null) {
            throw ConfigurationException::missingToken();
        }

        return $token;
    }

    public function sender(): ?string
    {
        return $this->nullableString('sender');
    }

    public function timezone(): string
    {
        return $this->nullableString('timezone') ?? 'Asia/Riyadh';
    }

    public function baseUri(): string
    {
        return $this->nullableString('base_uri') ?? 'https://api.taqnyat.sa';
    }

    public function timeout(): float
    {
        return $this->seconds('http.timeout', 10.0);
    }

    public function connectTimeout(): float
    {
        return $this->seconds('http.connect_timeout', 5.0);
    }

    /**
     * @return array<string, string>
     */
    public function templates(): array
    {
        $templates = $this->get('templates');

        if ($templates === null) {
            return [];
        }

        if (!is_array($templates)) {
            throw ConfigurationException::invalidValue('templates', 'an array of name => body');
        }

        $result = [];

        foreach ($templates as $name => $body) {
            if (!is_string($body)) {
                throw ConfigurationException::invalidValue('templates.' . $name, 'a string');
            }

            $result[(string) $name] = $body;
        }

        return $result;
    }

    /**
     * Returns the trimmed string value, or null when it is missing or blank.
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw ConfigurationException::invalidValue($key, 'a string');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function seconds(string $key, float $default): float
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            throw ConfigurationException::invalidValue($key, 'a number of seconds');
        }

        return (float) $value;
    }

    private function get(string $key): mixed
    {
        return $this->config->get('taqnyat.' . $key);
    }
}
