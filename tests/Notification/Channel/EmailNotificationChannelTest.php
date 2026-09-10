<?php

declare(strict_types=1);

namespace App\Tests\Notification\Channel;

use App\Notification\Channel\EmailNotificationChannel;
use App\Notification\Notification;
use App\Notification\NotificationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[CoversClass(EmailNotificationChannel::class)]
final class EmailNotificationChannelTest extends TestCase
{
    public function testItSupportsASavedProduct(): void
    {
        $channel = new EmailNotificationChannel(
            $this->createStub(MailerInterface::class),
            'no-reply@example.test',
            'team@example.test',
        );

        self::assertTrue($channel->supports(self::notification()));
    }

    public function testItSendsOneEmailBuiltFromTheNotification(): void
    {
        $sent = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$sent): void {
                $sent = $email;
            });

        $channel = new EmailNotificationChannel($mailer, 'no-reply@example.test', 'team@example.test');
        $channel->send(self::notification());

        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('no-reply@example.test', $sent->getFrom()[0]->getAddress());
        self::assertSame('team@example.test', $sent->getTo()[0]->getAddress());
        self::assertSame('Product saved: Desk Lamp', $sent->getSubject());
        self::assertSame('Product #1 "Desk Lamp" was saved.', $sent->getTextBody());
    }

    private static function notification(): Notification
    {
        return new Notification(
            type: NotificationType::ProductSaved,
            subject: 'Product saved: Desk Lamp',
            message: 'Product #1 "Desk Lamp" was saved.',
            context: ['product_id' => 1],
        );
    }
}
