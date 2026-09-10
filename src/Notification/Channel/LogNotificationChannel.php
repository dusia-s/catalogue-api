<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Entity\OperationLog;
use App\Notification\Notification;
use App\Notification\NotificationChannelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Records the operation, both as a durable row and on the `notification` log channel.
 *
 * The row is the audit trail the brief calls for; the log line is for whoever is
 * tailing the container output.
 */
final class LogNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Target('notificationLogger')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Everything gets logged — this is the audit trail, so it deliberately does not
     * filter by type the way a user-facing channel does.
     */
    public function supports(Notification $notification): bool
    {
        return true;
    }

    public function send(Notification $notification): void
    {
        $this->entityManager->persist(new OperationLog(
            $notification->type->value,
            $notification->message,
            $notification->context,
        ));

        // Safe to flush: the dispatcher runs after the product's own flush has
        // committed, so this is a separate transaction and cannot disturb it.
        $this->entityManager->flush();

        $this->logger->info($notification->message, [
            'type' => $notification->type->value,
            ...$notification->context,
        ]);
    }
}
