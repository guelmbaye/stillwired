<?php

declare(strict_types=1);

namespace Stillwired\NativeWebhook;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/** Runs synchronously by default: a 2xx reply follows durable receipt persistence. */
#[AsRemoteEventConsumer('stillwired_github')]
final class GitHubRemoteEventConsumer implements ConsumerInterface
{
    public function __construct(private readonly GitHubReceiptStore $store)
    {
    }

    public function consume(RemoteEvent $event): void
    {
        $this->store->record($event->getId(), $event->getName(), $event->getPayload());
    }
}
