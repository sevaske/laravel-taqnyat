<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Testing;

use PHPUnit\Framework\Assert;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Message\PhoneNumber;
use Sevaske\Taqnyat\Message\SmsMessage;
use Sevaske\Taqnyat\Response\SendSmsResponse;

/**
 * Records messages instead of sending them. Created by {@see \Sevaske\LaravelTaqnyat\Facades\Taqnyat::fake()}.
 *
 *     $sms = Taqnyat::fake();
 *
 *     // ... code under test ...
 *
 *     $sms->assertSentTo('966500000000', fn (SmsMessage $message) => str_contains($message->getBody(), '1234'));
 */
final class FakeSmsSender implements SmsSender
{
    /**
     * @var list<SmsMessage>
     */
    private array $messages = [];

    private int $lastMessageId = 0;

    private ?\Throwable $exceptionToThrow = null;

    public function send(SmsMessage $message): SendSmsResponse
    {
        if ($this->exceptionToThrow !== null) {
            throw $this->exceptionToThrow;
        }

        $this->messages[] = $message;

        return $this->createResponse($message);
    }

    /**
     * Makes every following send() throw the given exception, e.g. to test error handling:
     *
     *     Taqnyat::fake()->throwOnSend(new AuthenticationException('Invalid token', 401));
     */
    public function throwOnSend(?\Throwable $exception): self
    {
        $this->exceptionToThrow = $exception;

        return $this;
    }

    /**
     * Recorded messages, optionally filtered by a callback.
     *
     * @param (callable(SmsMessage): bool)|null $callback
     *
     * @return list<SmsMessage>
     */
    public function sent(?callable $callback = null): array
    {
        if ($callback === null) {
            return $this->messages;
        }

        $matching = [];

        foreach ($this->messages as $message) {
            if ($callback($message)) {
                $matching[] = $message;
            }
        }

        return $matching;
    }

    /**
     * Recorded messages addressed to the given phone number (any format accepted by PhoneNumber).
     *
     * @param (callable(SmsMessage): bool)|null $callback
     *
     * @return list<SmsMessage>
     */
    public function sentTo(string|int|PhoneNumber $phoneNumber, ?callable $callback = null): array
    {
        $number = PhoneNumber::from($phoneNumber)->toString();

        return $this->sent(static function (SmsMessage $message) use ($number, $callback): bool {
            $isAddressedToNumber = in_array($number, $message->getRecipientNumbers(), true);

            if (!$isAddressedToNumber) {
                return false;
            }

            return $callback === null || $callback($message);
        });
    }

    /**
     * @param (callable(SmsMessage): bool)|null $callback
     */
    public function assertSent(?callable $callback = null): void
    {
        $failureMessage = $callback === null
            ? 'Expected an SMS to be sent, but none was sent.'
            : 'Expected an SMS matching the given callback to be sent, but none was sent.';

        Assert::assertNotEmpty($this->sent($callback), $failureMessage);
    }

    /**
     * @param (callable(SmsMessage): bool)|null $callback
     */
    public function assertSentTo(string|int|PhoneNumber $phoneNumber, ?callable $callback = null): void
    {
        $number = PhoneNumber::from($phoneNumber)->toString();

        Assert::assertNotEmpty(
            $this->sentTo($number, $callback),
            sprintf('Expected an SMS to be sent to %s, but none was sent.', $number),
        );
    }

    public function assertNotSentTo(string|int|PhoneNumber $phoneNumber): void
    {
        $number = PhoneNumber::from($phoneNumber)->toString();

        Assert::assertEmpty(
            $this->sentTo($number),
            sprintf('Expected no SMS to be sent to %s, but one was sent.', $number),
        );
    }

    /**
     * @param callable(SmsMessage): bool $callback
     */
    public function assertNotSent(callable $callback): void
    {
        Assert::assertEmpty(
            $this->sent($callback),
            'Expected no SMS matching the given callback to be sent, but one was sent.',
        );
    }

    public function assertNothingSent(): void
    {
        $count = count($this->messages);

        Assert::assertSame(0, $count, sprintf('Expected no SMS to be sent, but %d were sent.', $count));
    }

    public function assertSentCount(int $expectedCount): void
    {
        $count = count($this->messages);

        Assert::assertSame(
            $expectedCount,
            $count,
            sprintf('Expected %d SMS to be sent, but %d were sent.', $expectedCount, $count),
        );
    }

    private function createResponse(SmsMessage $message): SendSmsResponse
    {
        $this->lastMessageId++;

        $recipients = $message->getRecipientNumbers();

        return new SendSmsResponse(
            201,
            (string) $this->lastMessageId,
            '0',
            'SAR',
            count($recipients),
            mb_strlen($message->getBody()),
            $recipients,
            [],
        );
    }
}
