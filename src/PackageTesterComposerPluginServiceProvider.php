<?php

namespace JobMetric\PackageTesterComposerPlugin;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use JobMetric\PackageTesterComposerPlugin\Extra\ConfigExtra;

class PackageTesterComposerPluginServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ConfigExtra::class, function () {
            return new ConfigExtra(base_path());
        });
    }
}
