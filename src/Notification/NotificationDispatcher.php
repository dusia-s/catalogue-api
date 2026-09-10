<?php

declare(strict_types=1);

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Fans a notification out to every channel that supports it.
 */
final class NotificationDispatcher
{
    /**
     * @param iterable<NotificationChannelInterface> $channels every service tagged
     *                                                         app.notification_channel
     */
    public function __construct(
        #[AutowireIterator('app.notification_channel')]
        private readonly iterable $channels,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(Notification $notification): void
    {
        foreach ($this->channels as $channel) {
            if (!$channel->supports($notification)) {
                continue;
            }

            try {
                $channel->send($notification);
            } catch (\Throwable $exception) {
                // Channels are independent: a bounced e-mail must not cost us the
                // operation log, and neither must cost the caller its response. The
                // product is already committed by the time we get here.
                $this->logger->error('Notification channel failed', [
                    'channel' => $channel::class,
                    'type' => $notification->type->value,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
