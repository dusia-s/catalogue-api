<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Notification;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends the notification as an e-mail.
 *
 * MAILER_DSN points at Mailpit in this stack, so nothing leaves the machine while
 * the wiring stays exactly what a real SMTP transport would need.
 */
final class EmailNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(NOTIFICATION_EMAIL_FROM)%')]
        private readonly string $from,
        #[Autowire('%env(NOTIFICATION_EMAIL_TO)%')]
        private readonly string $to,
    ) {
    }

    /**
     * Unlike the audit log, e-mail is opt-in per notification kind: a future
     * internal-only notification should not mail anyone by default.
     */
    public function supports(Notification $notification): bool
    {
        return NotificationType::ProductSaved === $notification->type;
    }

    public function send(Notification $notification): void
    {
        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($this->to)
                ->subject($notification->subject)
                ->text($notification->message)
        );
    }
}
