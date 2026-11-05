<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Console;

use Sevaske\LaravelTaqnyat\Taqnyat;
use Sevaske\Taqnyat\Response\SendSmsResponse;

final class SendCommand extends TaqnyatCommand
{
    /**
     * @var string
     */
    protected $signature = 'taqnyat:send
        {recipients* : One or more phone numbers in international format}
        {--message= : Message text}
        {--template= : Template name from config("taqnyat.templates")}
        {--param=* : Template parameter as name=value (repeatable)}
        {--sender= : Sender name (defaults to config("taqnyat.sender"))}';

    /**
     * @var string
     */
    protected $description = 'Send an SMS through Taqnyat';

    protected function handleWith(Taqnyat $taqnyat): int
    {
        $recipients = $this->stringListArgument('recipients');
        $text = $this->stringOption('message');
        $templateName = $this->stringOption('template');
        $sender = $this->stringOption('sender');

        if (($text === null) === ($templateName === null)) {
            $this->error('Pass either --message or --template.');

            return self::FAILURE;
        }

        if ($templateName !== null) {
            $parameters = $this->templateParameters();
            $response = $taqnyat->sendTemplate($recipients, $templateName, $parameters, $sender);
        } else {
            $response = $taqnyat->sendText($recipients, (string) $text, $sender);
        }

        $this->printResponse($response);

        return self::SUCCESS;
    }

    private function printResponse(SendSmsResponse $response): void
    {
        $this->info('Message sent. ID: ' . $response->getMessageId());

        $this->table(['Field', 'Value'], [
            ['Cost', $response->getCost() . ' ' . $response->getCurrency()],
            ['Accepted', implode(', ', $response->getAccepted())],
            ['Rejected', implode(', ', $response->getRejected())],
        ]);

        if ($response->hasRejected()) {
            $this->warn('Some recipients were rejected.');
        }
    }

    /**
     * Parses repeated --param=name=value options.
     *
     * @return array<string, string>
     */
    private function templateParameters(): array
    {
        $parameters = [];

        foreach ($this->stringListOption('param') as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parameters[trim($name)] = $value;
        }

        return $parameters;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function stringListOption(string $name): array
    {
        return self::toStringList($this->option($name));
    }

    /**
     * @return list<string>
     */
    private function stringListArgument(string $name): array
    {
        return self::toStringList($this->argument($name));
    }

    /**
     * @return list<string>
     */
    private static function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
