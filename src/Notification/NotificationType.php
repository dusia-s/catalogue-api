<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * The kinds of notification the application can raise.
 *
 * Channels filter on this in {@see NotificationChannelInterface::supports()}, so a
 * new notification kind does not force every existing channel to handle it.
 */
enum NotificationType: string
{
    case ProductSaved = 'product.saved';
}
