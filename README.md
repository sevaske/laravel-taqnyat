# Taqnyat SMS for Laravel

[![CI](https://github.com/sevaske/laravel-taqnyat/actions/workflows/ci.yml/badge.svg)](https://github.com/sevaske/laravel-taqnyat/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/sevaske/laravel-taqnyat.svg)](https://packagist.org/packages/sevaske/laravel-taqnyat)
[![PHP Version](https://img.shields.io/packagist/php-v/sevaske/laravel-taqnyat.svg)](https://packagist.org/packages/sevaske/laravel-taqnyat)
[![License](https://img.shields.io/packagist/l/sevaske/laravel-taqnyat.svg)](LICENSE)

Laravel integration for the [Taqnyat](https://www.taqnyat.sa) [SMS API](https://dev.taqnyat.sa/en/doc/sms/),
built on top of the framework-agnostic [`sevaske/taqnyat-api`](https://github.com/sevaske/taqnyat-api) client.

- `Taqnyat` facade with a default sender and templates from config
- Notification channel: send notifications as SMS
- `Taqnyat::fake()` with assertions for your tests
- Events, artisan commands (`taqnyat:send`, `taqnyat:balance`, `taqnyat:senders`, `taqnyat:status`)
- Laravel 10 – 13, PHP 8.1 – 8.5

> This is an unofficial, community-maintained package and is not affiliated with Taqnyat.

## Installation

```bash
composer require sevaske/laravel-taqnyat
```

The service provider and the `Taqnyat` facade are registered automatically (package discovery).

Add your credentials to `.env`:

```dotenv
TAQNYAT_TOKEN=your-bearer-token
TAQNYAT_SENDER=YourSenderName
```

To get a bearer token, log in to your Taqnyat account, open **Developer → Application**, add an application
with the SMS service enabled and copy its bearer token
(see [Setup Bearer token](https://dev.taqnyat.sa/en/doc/sms/#setup-bearer)).
The sender must be an active sender name in your account. List them with `php artisan taqnyat:senders`.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=taqnyat-config
```

| Env variable              | Default                  | Description                                    |
|---------------------------|--------------------------|------------------------------------------------|
| `TAQNYAT_TOKEN`           | none                     | Bearer token (required)                        |
| `TAQNYAT_SENDER`          | none                     | Default sender name                            |
| `TAQNYAT_TIMEZONE`        | `Asia/Riyadh`            | Timezone scheduled dates are converted to      |
| `TAQNYAT_BASE_URI`        | `https://api.taqnyat.sa` | API base URI                                   |
| `TAQNYAT_TIMEOUT`         | `10`                     | Request timeout, seconds                       |
| `TAQNYAT_CONNECT_TIMEOUT` | `5`                      | Connection timeout, seconds                    |

## Sending SMS

```php
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;

// Plain text, default sender from config
Taqnyat::sendText('966500000000', 'Your order has shipped');

// Several recipients, explicit sender
Taqnyat::sendText(['+966 50 000 0000', '00966511111111'], 'Hello!', 'OtherSender');

// Scheduled message: build it with the default sender, then send
Taqnyat::send(
    Taqnyat::message('966500000000', 'Reminder: your appointment is tomorrow')
        ->scheduleAt(now()->addDay())
        ->withDeleteId(1001) // optional: lets you cancel it with Taqnyat::delete(1001)
);
```

Every send method returns `Sevaske\Taqnyat\Response\SendSmsResponse`:

```php
$response = Taqnyat::sendText($phone, 'Hello');

$response->getMessageId(); // "5829452722"
$response->getCost();      // "0.1500"
$response->hasRejected();  // true if some recipients were rejected
```

Phone numbers are normalized automatically (`+966…`, `00966…`, spaces and dashes are fine);
invalid numbers throw `Sevaske\Taqnyat\Exception\InvalidArgumentException` before any request is made.

## Templates

Define message bodies with `{placeholders}` in `config/taqnyat.php`:

```php
'templates' => [
    'otp'      => 'Your verification code is {code}. It expires in {minutes} minutes.',
    'shipping' => 'Hi {name}, your order #{order} has shipped.',
],
```

```php
Taqnyat::sendTemplate($user->phone, 'otp', ['code' => $code, 'minutes' => 5]);
```

Rendering is strict: a missing placeholder value throws an exception, so a customer never receives
`Your verification code is {code}`. You can also pass a `Sevaske\Taqnyat\Template\Template` instance
instead of a name.

## Notifications

Return `'taqnyat'` (or `TaqnyatChannel::class`) from `via()` and implement `toTaqnyat()`:

```php
use Illuminate\Notifications\Notification;
use Sevaske\LaravelTaqnyat\Notifications\TaqnyatMessage;

class OrderShipped extends Notification
{
    public function __construct(private Order $order) {}

    public function via($notifiable): array
    {
        return ['taqnyat'];
    }

    public function toTaqnyat($notifiable): TaqnyatMessage
    {
        return TaqnyatMessage::create("Your order #{$this->order->number} has shipped.");
    }
}
```

`toTaqnyat()` may return:

- a **string**: the message text;
- a **`TaqnyatMessage`**: text or template plus options (below);
- an **`Sevaske\Taqnyat\Message\SmsMessage`**: sent as is, with its own sender and recipients.

```php
TaqnyatMessage::create('Plain text')            // or ->content('...')
    ->template('shipping', ['name' => 'Sara', 'order' => 1042]) // instead of text
    ->sender('OtherSender')                     // instead of TAQNYAT_SENDER
    ->to('966511111111')                        // instead of the notifiable's phone
    ->scheduleAt(now()->addHour())
    ->deleteId(1001);
```

Tell Laravel where to send SMS for your notifiable:

```php
class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForTaqnyat($notification): ?string
    {
        return $this->phone; // a string, or an array of numbers
    }
}
```

If the route is empty, the notification is skipped for that notifiable without an error.

On-demand notifications:

```php
use Illuminate\Support\Facades\Notification;

Notification::route('taqnyat', '966500000000')->notify(new OrderShipped($order));
```

Queued notifications (`ShouldQueue`) work as usual.

## Other endpoints

```php
Taqnyat::balance();   // BalanceResponse: getBalance(), getCurrency(), getPoints(), getExpiryDate()...
Taqnyat::senders();   // SendersResponse: iterable list of Sender, getNames(), getActive(), find()...
Taqnyat::status();    // SystemStatusResponse: isOperational(), getDescription()
Taqnyat::delete(1001); // deletes a scheduled message by its delete ID
```

See the [`sevaske/taqnyat-api` documentation](https://github.com/sevaske/taqnyat-api#readme) for all response methods.

## Dependency injection

Instead of the facade you can inject:

| Class                                     | What it is                                                         |
|-------------------------------------------|--------------------------------------------------------------------|
| `Sevaske\LaravelTaqnyat\Taqnyat`          | The service behind the facade (default sender, templates, events)  |
| `Sevaske\Taqnyat\Contracts\SmsSender`     | Minimal `send(SmsMessage)` contract, faked by `Taqnyat::fake()`    |
| `Sevaske\Taqnyat\Client`                  | The underlying framework-agnostic client                           |
| `Sevaske\Taqnyat\Template\TemplateRegistry` | Templates from config                                            |

```php
use Sevaske\LaravelTaqnyat\Taqnyat;

class SendOtp
{
    public function __construct(private Taqnyat $taqnyat) {}

    public function __invoke(User $user, string $code): void
    {
        $this->taqnyat->sendTemplate($user->phone, 'otp', ['code' => $code]);
    }
}
```

## Events

| Event                                         | When                        | Properties                 |
|-----------------------------------------------|-----------------------------|----------------------------|
| `Sevaske\LaravelTaqnyat\Events\MessageSending` | Before a message is sent   | `$message`                 |
| `Sevaske\LaravelTaqnyat\Events\MessageSent`    | After Taqnyat accepted it  | `$message`, `$response`    |

Events are dispatched for messages sent through the facade, the `Taqnyat` service and the notification channel.

## Artisan commands

```bash
php artisan taqnyat:status     # service status (exit code 1 during maintenance)
php artisan taqnyat:balance    # account balance, points, expiry date
php artisan taqnyat:senders    # your sender names and their status
php artisan taqnyat:send 966500000000 --message="Hello"
php artisan taqnyat:send 966500000000 966511111111 --template=otp --param=code=1234 --sender=MyBrand
```

## Testing

`Taqnyat::fake()` replaces real sending with a fake that records messages. No HTTP requests are made
and no token is needed:

```php
use Sevaske\LaravelTaqnyat\Facades\Taqnyat;
use Sevaske\Taqnyat\Message\SmsMessage;

public function test_otp_is_sent(): void
{
    $sms = Taqnyat::fake();

    $this->post('/login/otp', ['phone' => '+966500000000']);

    $sms->assertSentTo('966500000000', fn (SmsMessage $message) => str_contains($message->getBody(), 'code'));
    $sms->assertSentCount(1);
}
```

Available assertions: `assertSent(?callable)`, `assertSentTo($phone, ?callable)`, `assertNotSentTo($phone)`,
`assertNotSent(callable)`, `assertNothingSent()`, `assertSentCount(int)`. Use `sent(?callable)` and
`sentTo($phone)` to inspect the messages yourself.

Simulate failures:

```php
use Sevaske\Taqnyat\Exception\AuthenticationException;

Taqnyat::fake()->throwOnSend(new AuthenticationException('invalid credentials information.', 401));
```

The fake covers the facade, the `Taqnyat` service, the notification channel and the `SmsSender` contract.
Other endpoints (`balance()`, `senders()`...) and a directly injected `Sevaske\Taqnyat\Client` are not faked.

## Customizing the HTTP client

By default a Guzzle client with the configured timeouts is used. Rebind it to add a proxy, retries or logging:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Sevaske\LaravelTaqnyat\TaqnyatServiceProvider;

// In AppServiceProvider::register()
$this->app->singleton(TaqnyatServiceProvider::HTTP_CLIENT, function () {
    $stack = HandlerStack::create();
    $stack->push(Middleware::retry(fn ($retries, $request, $response, $error) => $retries < 2 && $error !== null));

    return new Client(['handler' => $stack, 'timeout' => 10]);
});
```

Any [PSR-18](https://www.php-fig.org/psr/psr-18/) client works.

## Error handling

All exceptions implement `Sevaske\Taqnyat\Exception\TaqnyatException`. API errors are thrown as subclasses of
`ApiException` (`AuthenticationException`, `BadRequestException`...); see the
[core library docs](https://github.com/sevaske/taqnyat-api#error-handling). Missing configuration
(token or sender) throws `Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException`, but only when the
value is actually needed, so your application boots without a token.

## Development

```bash
composer install
composer test           # PHPUnit (Orchestra Testbench)
composer analyse        # PHPStan (level max)
composer pint:install   # once, requires PHP 8.3+
composer format         # Laravel Pint
```

## License

The MIT License (MIT). See [LICENSE](LICENSE).
