<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProductSavedNotification;
use App\Notification\Notification;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Turns a saved-product message into a channel-agnostic notification.
 *
 * Keeping the wording here means the channels stay dumb transports and the
 * processor stays free of presentation concerns.
 */
#[AsMessageHandler]
final class ProductSavedNotificationHandler
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function __invoke(ProductSavedNotification $message): void
    {
        $categories = $message->categoryCodes;

        $this->dispatcher->dispatch(new Notification(
            type: NotificationType::ProductSaved,
            subject: \sprintf('Product saved: %s', $message->productName),
            message: \sprintf(
                'Product #%d "%s" (price %s) was saved with %d category/categories: %s.',
                $message->productId,
                $message->productName,
                $message->price,
                \count($categories),
                $categories ? implode(', ', $categories) : '-',
            ),
            context: [
                'product_id' => $message->productId,
                'product_name' => $message->productName,
                'price' => $message->price,
                'category_codes' => $categories,
            ],
        ));
    }
}
