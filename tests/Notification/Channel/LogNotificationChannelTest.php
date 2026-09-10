<?php

declare(strict_types=1);

namespace App\Tests\Notification\Channel;

use App\Entity\OperationLog;
use App\Notification\Channel\LogNotificationChannel;
use App\Notification\Notification;
use App\Notification\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(LogNotificationChannel::class)]
final class LogNotificationChannelTest extends TestCase
{
    /**
     * The audit trail deliberately takes everything, so it does not need updating
     * each time a new notification type is introduced.
     */
    public function testItSupportsEveryNotification(): void
    {
        $channel = new LogNotificationChannel(
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
        );

        self::assertTrue($channel->supports(self::notification()));
    }

    public function testItPersistsAnOperationLogCarryingTheNotificationPayload(): void
    {
        $persisted = null;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        (new LogNotificationChannel($entityManager, new NullLogger()))->send(self::notification());

        self::assertInstanceOf(OperationLog::class, $persisted);
        self::assertSame('product.saved', $persisted->getType());
        self::assertSame('Product #1 "Desk Lamp" was saved.', $persisted->getMessage());
        self::assertSame(['product_id' => 1], $persisted->getContext());
    }

    public function testItAlsoWritesToTheNotificationLogChannel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Product #1 "Desk Lamp" was saved.',
                ['type' => 'product.saved', 'product_id' => 1],
            );

        $channel = new LogNotificationChannel(
            $this->createStub(EntityManagerInterface::class),
            $logger,
        );

        $channel->send(self::notification());
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
