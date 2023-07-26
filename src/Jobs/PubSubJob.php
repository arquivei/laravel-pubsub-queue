<?php

namespace Arquivei\PubSubQueue\Jobs;

use Google\Cloud\PubSub\Message;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Arquivei\PubSubQueue\PubSubQueue;

class PubSubJob extends Job implements JobContract
{
    protected PubSubQueue $pubsub;
    protected Message $job;
    protected array $decoded;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Container $container,
        PubSubQueue $pubsub,
        Message $job,
        ?string $connectionName,
        string $queue
    ) {
        $this->pubsub = $pubsub;
        $this->job = $job;
        $this->queue = $queue;
        $this->container = $container;
        $this->connectionName = $connectionName;

        $this->decoded = $this->payload();
    }

    /**
     * Get the job identifier.
     */
    public function getJobId(): ?string
    {
        return $this->decoded['id'] ?? null;
    }

    /**
     * Get the raw body of the job.
     */
    public function getRawBody(): string
    {
        return base64_decode($this->job->data());
    }

    /**
     * Get the number of times the job has been attempted.
     */
    public function attempts(): int
    {
        return ((int) $this->job->attribute('attempts') ?? 0) + 1;
    }

    /**
     * Release the job back into the queue.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);
    }

    /**
     * Fire the job.
     */
    public function fire(): void
    {
        parent::fire();
        $this->pubsub->acknowledge($this->job, $this->queue);
    }
}
