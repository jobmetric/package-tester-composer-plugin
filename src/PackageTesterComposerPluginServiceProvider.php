<?php

namespace JobMetric\PackageTesterComposerPlugin;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use JobMetric\PackageTesterComposerPlugin\Extra\ConfigExtra;

/**
 * Class PackageTesterComposerPluginServiceProvider
 *
 * Laravel service provider for the package-tester-composer-plugin.
 * Registers the ConfigExtra class as a singleton in the service container.
 *
 * @package JobMetric\PackageTesterComposerPlugin
 */
class PackageTesterComposerPluginServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * Binds the ConfigExtra class as a singleton to the service container,
     * using the application's base path for configuration storage.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            ConfigExtra::class, fn(Application $app): ConfigExtra => new ConfigExtra($app->basePath())
        );
    }
}
