<?php

namespace Arquivei\PubSubQueue;

use Arquivei\PubSubQueue\Connectors\PubSubConnector;
use Illuminate\Support\ServiceProvider;

class PubSubQueueServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->app['queue']->addConnector('pubsub', function () {
            return new PubSubConnector();
        });
    }
}