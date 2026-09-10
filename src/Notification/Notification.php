<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * A channel-agnostic notification.
 *
 * Deliberately carries no transport detail — no recipients, no formatting — so the
 * same instance can be handed to an e-mail, a log, a Slack post or an SMS without
 * any of them needing to know about the others.
 */
final readonly class Notification
{
    /**
     * @param array<string, mixed> $context structured payload for channels that can use it
     */
    public function __construct(
        public NotificationType $type,
        public string $subject,
        public string $message,
        public array $context = [],
    ) {
    }
}
