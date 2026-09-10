<?php

declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A delivery mechanism for notifications.
 *
 * This is the extension point the brief asks for. Adding Slack or SMS means adding
 * one class implementing this interface — the tag below is applied automatically by
 * autoconfiguration, {@see NotificationDispatcher} picks it up through its tagged
 * iterator, and no existing file needs to change.
 */
#[AutoconfigureTag('app.notification_channel')]
interface NotificationChannelInterface
{
    /**
     * Whether this channel handles the given notification.
     */
    public function supports(Notification $notification): bool;

    /**
     * Deliver the notification. May throw; the dispatcher isolates failures.
     */
    public function send(Notification $notification): void;
}
