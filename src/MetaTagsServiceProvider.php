<?php

namespace ZanySoft\LaravelMetaTags;

use Illuminate\Support\ServiceProvider;

class MetaTagsServiceProvider extends ServiceProvider
{

    /**
     * Application is booting.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([$this->getConfigPath() => config_path('meta-tags.php')], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'meta-tags');

        $this->app->singleton('metatag', function ($app) {
            return new MetaTag($app['request'], $app['config']['meta-tags'], $app['config']->get('app.locale'));
        });
    }

    /**
     * @return string
     */
    protected function getConfigPath()
    {
        return dirname(__DIR__) . '/config/config.php';
    }
}
