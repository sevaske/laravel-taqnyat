<?php

declare(strict_types=1);

namespace Sevaske\LaravelTaqnyat\Tests\Feature;

use Illuminate\Notifications\ChannelManager;
use Psr\Http\Client\ClientInterface;
use Sevaske\LaravelTaqnyat\Exceptions\ConfigurationException;
use Sevaske\LaravelTaqnyat\Facades\Taqnyat as TaqnyatFacade;
use Sevaske\LaravelTaqnyat\Notifications\TaqnyatChannel;
use Sevaske\LaravelTaqnyat\Taqnyat;
use Sevaske\LaravelTaqnyat\TaqnyatServiceProvider;
use Sevaske\LaravelTaqnyat\Tests\TestCase;
use Sevaske\Taqnyat\Client;
use Sevaske\Taqnyat\Contracts\SmsSender;
use Sevaske\Taqnyat\Exception\TaqnyatException;
use Sevaske\Taqnyat\Template\TemplateRegistry;

final class ServiceProviderTest extends TestCase
{
    public function testRegistersServices(): void
    {
        self::assertInstanceOf(Client::class, $this->app->make(Client::class));
        self::assertSame($this->app->make(Client::class), $this->app->make(SmsSender::class));
        self::assertInstanceOf(ClientInterface::class, $this->app->make(TaqnyatServiceProvider::HTTP_CLIENT));
        self::assertSame($this->app->make(Taqnyat::class), $this->app->make('taqnyat'));
        self::assertSame($this->app->make(Taqnyat::class), TaqnyatFacade::getFacadeRoot());
    }

    public function testAppliesConfig(): void
    {
        $this->app['config']->set('taqnyat.timezone', 'UTC');
        $this->app['config']->set('taqnyat.base_uri', 'https://proxy.example.com/taqnyat');

        $options = $this->app->make(Client::class)->getOptions();

        self::assertSame('UTC', $options->getTimezone()->getName());
        self::assertSame('https://proxy.example.com/taqnyat', $options->getBaseUri());
        self::assertSame('DefaultSender', $this->app->make(Taqnyat::class)->getDefaultSender());
    }

    public function testLoadsTemplatesFromConfig(): void
    {
        $templates = $this->app->make(TemplateRegistry::class);

        self::assertSame(['otp'], $templates->names());
        self::assertSame('Your code is 42', $templates->render('otp', ['code' => 42]));
    }

    public function testMissingTokenFailsOnlyWhenTheClientIsUsed(): void
    {
        $this->app['config']->set('taqnyat.token', null);

        // The service itself can be resolved: sending may be faked in tests.
        $taqnyat = $this->app->make(Taqnyat::class);

        try {
            $taqnyat->sendText('966500000000', 'Hello');
            self::fail('Exception expected');
        } catch (ConfigurationException $e) {
            self::assertInstanceOf(TaqnyatException::class, $e);
            self::assertStringContainsString('TAQNYAT_TOKEN', $e->getMessage());
        }
    }

    public function testInvalidHttpClientBindingIsReported(): void
    {
        $this->app->instance(TaqnyatServiceProvider::HTTP_CLIENT, new \stdClass());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must be a PSR-18 HTTP client');

        $this->app->make(Client::class);
    }

    public function testInvalidConfigValueIsReported(): void
    {
        $this->app['config']->set('taqnyat.http.timeout', 'soon');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('taqnyat.http.timeout');

        $this->app->make(TaqnyatServiceProvider::HTTP_CLIENT);
    }

    public function testRegistersNotificationChannelAlias(): void
    {
        $channel = $this->app->make(ChannelManager::class)->driver('taqnyat');

        self::assertInstanceOf(TaqnyatChannel::class, $channel);
    }

    public function testConfigIsPublishable(): void
    {
        $paths = TaqnyatServiceProvider::pathsToPublish(TaqnyatServiceProvider::class, 'taqnyat-config');

        self::assertCount(1, $paths);
        self::assertFileExists((string) array_key_first($paths));
    }
}
