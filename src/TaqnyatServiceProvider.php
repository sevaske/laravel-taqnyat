<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Sevaske\LaravelTaqnyat\Console\BalanceCommand;
use Sevaske\LaravelTaqnyat\Console\SendCommand;
use Sevaske\LaravelTaqnyat\Console\SendersCommand;
use Sevaske\LaravelTaqnyat\Console\StatusCommand;
use Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException;
use Sevaske\LaravelTaqnyat\Notifications\TaqnyatChannel;
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\ClientOptions;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Template\TemplateRegistry;

final class TaqnyatServiceProvider extends ServiceProvider
{
    /**
     * Container key of the PSR-18 HTTP client used to talk to Taqnyat.
     * Rebind it to customize the client (proxy, retries, logging middleware).
     */
    public const HTTP_CLIENT = 'taqnyat.http_client';

    private const CONFIG_PATH = __DIR__ . '/../config/taqnyat.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'taqnyat');

        $this->registerConfig();
        $this->registerHttpClient();
        $this->registerClient();
        $this->registerTemplates();
        $this->registerTaqnyat();
    }

    public function boot(): void
    {
        $this->registerNotificationChannel();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->registerCommands();
        }
    }

    private function registerConfig(): void
    {
        $this->app->singleton(TaqnyatConfig::class, static function (Container $app): TaqnyatConfig {
            return new TaqnyatConfig($app->make(Repository::class));
        });
    }

    private function registerHttpClient(): void
    {
        $this->app->singleton(self::HTTP_CLIENT, static function (Container $app): ClientInterface {
            $config = $app->make(TaqnyatConfig::class);

            return new GuzzleClient([
                'timeout' => $config->timeout(),
                'connect_timeout' => $config->connectTimeout(),
            ]);
        });
    }

    private function registerClient(): void
    {
        $this->app->singleton(Client::class, static function (Container $app): Client {
            $config = $app->make(TaqnyatConfig::class);

            $options = ClientOptions::create()
                ->withHttpClient(self::resolveHttpClient($app))
                ->withBaseUri($config->baseUri())
                ->withTimezone($config->timezone());

            return new Client($config->token(), $options);
        });

        $this->app->singleton(SmsSender::class, static function (Container $app): SmsSender {
            return $app->make(Client::class);
        });
    }

    private static function resolveHttpClient(Container $app): ClientInterface
    {
        $httpClient = $app->make(self::HTTP_CLIENT);

        if (!$httpClient instanceof ClientInterface) {
            $message = sprintf('The "%s" binding must be a PSR-18 HTTP client (%s).', self::HTTP_CLIENT, ClientInterface::class);

            throw new ConfigurationException($message);
        }

        return $httpClient;
    }

    private function registerTemplates(): void
    {
        $this->app->singleton(TemplateRegistry::class, static function (Container $app): TemplateRegistry {
            $config = $app->make(TaqnyatConfig::class);

            return new TemplateRegistry($config->templates());
        });
    }

    private function registerTaqnyat(): void
    {
        $this->app->singleton(Taqnyat::class, static function (Container $app): Taqnyat {
            $resolveSmsSender = static function () use ($app): SmsSender {
                return $app->make(SmsSender::class);
            };

            $resolveClient = static function () use ($app): Client {
                return $app->make(Client::class);
            };

            return new Taqnyat(
                $resolveSmsSender,
                $resolveClient,
                $app->make(TemplateRegistry::class),
                $app->make(TaqnyatConfig::class)->sender(),
                $app->make(Dispatcher::class),
            );
        });

        $this->app->alias(Taqnyat::class, 'taqnyat');
    }

    /**
     * Allows via() to return 'taqnyat' in addition to TaqnyatChannel::class.
     */
    private function registerNotificationChannel(): void
    {
        $this->callAfterResolving(ChannelManager::class, static function (ChannelManager $channels): void {
            // Not static: the channel manager binds custom creators to itself.
            $channels->extend(TaqnyatChannel::NAME, function (Container $app): TaqnyatChannel {
                return $app->make(TaqnyatChannel::class);
            });
        });
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->app->configPath('taqnyat.php'),
        ], 'taqnyat-config');
    }

    private function registerCommands(): void
    {
        $this->commands([
            BalanceCommand::class,
            SendCommand::class,
            SendersCommand::class,
            StatusCommand::class,
        ]);
    }
}
