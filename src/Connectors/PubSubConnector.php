<?php

namespace Arquivei\PubSubQueue\Connectors;

use Google\Cloud\PubSub\PubSubClient;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Support\Str;
use Arquivei\PubSubQueue\PubSubQueue;

class PubSubConnector implements ConnectorInterface
{
    /**
     * Establish a queue connection.
     */
    public function connect(array $config): PubSubQueue
    {
        $gcpConfig = $this->transformConfig($config);

        return new PubSubQueue(
            new PubSubClient($gcpConfig),
            $config['queue'] ?? '',
            $config['subscriber'] ?? '',
            $config['create_topics'] ?? true,
            $config['create_subscriptions'] ?? true,
            $config['queue_prefix'] ?? ''
        );
    }

    /**
     * Transform the config to key => value array.
     */
    protected function transformConfig(array $config): array
    {
        return array_reduce(array_map([$this, 'transformConfigKeys'], $config, array_keys($config)), function ($carry, $item) {
            $carry[$item[0]] = $item[1];
            return $carry;
        }, []);
    }

    /**
     * Transform the keys of config to camelCase.
     */
    protected function transformConfigKeys(string $item, string $key): array
    {
        return [Str::camel($key), $item];
    }
}
