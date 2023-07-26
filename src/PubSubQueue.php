<?php

namespace Arquivei\PubSubQueue;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Google\Cloud\PubSub\Subscription;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\Job;
use Arquivei\PubSubQueue\Jobs\PubSubJob;

class PubSubQueue extends Queue implements QueueContract
{
    /**
     * Create a new GCP PubSub instance.
     */
    public function __construct(
        private readonly PubSubClient $pubsub,
        private readonly string $default,
        private readonly string $subscriber,
        private readonly bool $topicAutoCreation,
        private readonly bool $subscriptionAutoCreation,
        private readonly string $queuePrefix
    ) {
    }

    /**
     * Get the size of the queue.
     * PubSubClient have no method to retrieve the size of the queue.
     * To be updated if the API allow to get that data.
     */
    public function size($queue = null): int
    {
        return 0;
    }

    /**
     * Push a new job onto the queue.
     */
    public function push($job, $data = '', $queue = null): string
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $queue);
    }

    /**
     * Push a raw payload onto the queue.
     */
    public function pushRaw($payload, $queue = null, array $options = []): string
    {
        $topic = $this->getTopic($queue, $this->topicAutoCreation);

        $this->subscribeToTopic($topic);

        $publish = ['data' => base64_encode($payload)];

        if (! empty($options)) {
            $publish['attributes'] = $this->validateMessageAttributes($options);
        }

        $topic->publish($publish);

        $decodedPayload = json_decode($payload, true);

        return $decodedPayload['id'];
    }

    /**
     * Push a new job onto the queue after a delay.
     */
    public function later($delay, $job, $data = '', $queue = null): string
    {
        return $this->pushRaw(
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue
        );
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop($queue = null): ?Job
    {
        $topic = $this->getTopic($this->getQueue($queue));

        if ($this->topicAutoCreation && ! $topic->exists()) {
            return null;
        }

        $subscription = $topic->subscription($this->getSubscriberName());
        $messages = $subscription->pull([
            'returnImmediately' => true,
            'maxMessages' => 1,
        ]);

        if (empty($messages) || count($messages) < 1) {
            return null;
        }

        return new PubSubJob(
            $this->container,
            $this,
            $messages[0],
            $this->connectionName,
            $this->getQueue($queue)
        );
    }

    /**
     * Push an array of jobs onto the queue.
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $payloads = [];

        foreach ((array) $jobs as $job) {
            $payload = $this->createPayload($job, $this->getQueue($queue), $data);
            $payloads[] = ['data' => base64_encode($payload)];
        }

        $topic = $this->getTopic($this->getQueue($queue), $this->topicAutoCreation);

        $this->subscribeToTopic($topic);

        return $topic->publishBatch($payloads);
    }

    /**
     * Acknowledge a message.
     */
    public function acknowledge(Message $message, string $queue): void
    {
        $subscription = $this->getTopic($queue)->subscription($this->getSubscriberName());
        $subscription->acknowledge($message);
    }

    /**
     * Create a payload array from the given job and data.
     */
    protected function createPayloadArray($job, $queue, $data = ''): array
    {
        return array_merge(parent::createPayloadArray($job, $this->getQueue($queue), $data), [
            'id' => $this->getRandomId(),
        ]);
    }

    /**
     * Check if the attributes array only contains key-values
     * pairs made of strings.
     * @throws \UnexpectedValueException
     */
    private function validateMessageAttributes(array $attributes): array
    {
        $attributes_values = array_filter($attributes, 'is_string');

        if (count($attributes_values) !== count($attributes)) {
            throw new \UnexpectedValueException('PubSubMessage attributes only accept key-value pairs and all values must be string.');
        }

        $attributes_keys = array_filter(array_keys($attributes), 'is_string');

        if (count($attributes_keys) !== count(array_keys($attributes))) {
            throw new \UnexpectedValueException('PubSubMessage attributes only accept key-value pairs and all keys must be string.');
        }

        return $attributes;
    }

    /**
     * Get the current topic.
     */
    public function getTopic(string $queue, ?bool $create = false): Topic
    {
        $topic = $this->pubsub->topic($this->getQueue($queue));

        // don't check topic if automatic creation is not required, to avoid additional administrator operations calls
        if ($create && ! $topic->exists()) {
            $topic->create();
        }

        return $topic;
    }

    /**
     * Create a new subscription to a topic.
     */
    public function subscribeToTopic(Topic $topic): Subscription
    {
        $subscription = $topic->subscription($this->getSubscriberName());

        // don't check subscription if automatic creation is not required, to avoid additional administrator operations calls
        if ($this->subscriptionAutoCreation && ! $subscription->exists()) {
            $subscription = $topic->subscribe($this->getSubscriberName());
        }

        return $subscription;
    }

    /**
     * Get subscriber name.
     */
    public function getSubscriberName(): string
    {
        return $this->subscriber;
    }

    /**
     * Get the queue or return the default.
     */
    public function getQueue(?string $queue): string
    {
        $queue = $queue ?: $this->default;

        if (! $this->queuePrefix || Str::startsWith($queue, $this->queuePrefix)) {
            return $queue;
        }

        return $this->queuePrefix.$queue;
    }

    /**
     * Get a random ID string.
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }
}
