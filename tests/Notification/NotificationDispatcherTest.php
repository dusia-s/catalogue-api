<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\Notification;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(NotificationDispatcher::class)]
final class NotificationDispatcherTest extends TestCase
{
    public function testItSendsToEveryChannelThatSupportsTheNotification(): void
    {
        $notification = self::notification();

        $first = $this->createMock(NotificationChannelInterface::class);
        $first->method('supports')->willReturn(true);
        $first->expects(self::once())->method('send')->with($notification);

        $second = $this->createMock(NotificationChannelInterface::class);
        $second->method('supports')->willReturn(true);
        $second->expects(self::once())->method('send')->with($notification);

        $this->dispatcher([$first, $second])->dispatch($notification);
    }

    public function testItSkipsChannelsThatDoNotSupportTheNotification(): void
    {
        $declining = $this->createMock(NotificationChannelInterface::class);
        $declining->method('supports')->willReturn(false);
        $declining->expects(self::never())->method('send');

        $this->dispatcher([$declining])->dispatch(self::notification());
    }

    /**
     * The channels are independent: delivery happens after the product is already
     * committed, so a failing e-mail must still leave the operation log written.
     */
    public function testAFailingChannelDoesNotPreventTheOthersFromReceiving(): void
    {
        $failing = $this->createStub(NotificationChannelInterface::class);
        $failing->method('supports')->willReturn(true);
        $failing->method('send')->willThrowException(new \RuntimeException('SMTP is down'));

        $healthy = $this->createMock(NotificationChannelInterface::class);
        $healthy->method('supports')->willReturn(true);
        $healthy->expects(self::once())->method('send');

        $this->dispatcher([$failing, $healthy])->dispatch(self::notification());
    }

    public function testAFailingChannelIsSwallowedRatherThanBubbledToTheCaller(): void
    {
        $failing = $this->createStub(NotificationChannelInterface::class);
        $failing->method('supports')->willReturn(true);
        $failing->method('send')->willThrowException(new \RuntimeException('SMTP is down'));

        $this->dispatcher([$failing])->dispatch(self::notification());

        // Reaching this line is the assertion: dispatch() returned normally.
        $this->expectNotToPerformAssertions();
    }

    public function testAFailingChannelIsLoggedWithItsClassAndNotificationType(): void
    {
        $failing = $this->createStub(NotificationChannelInterface::class);
        $failing->method('supports')->willReturn(true);
        $failing->method('send')->willThrowException(new \RuntimeException('SMTP is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Notification channel failed',
                self::callback(static fn (array $context): bool => $failing::class === $context['channel']
                    && NotificationType::ProductSaved->value === $context['type']
                    && $context['exception'] instanceof \RuntimeException),
            );

        (new NotificationDispatcher([$failing], $logger))->dispatch(self::notification());
    }

    /**
     * @param list<NotificationChannelInterface> $channels
     */
    private function dispatcher(array $channels): NotificationDispatcher
    {
        return new NotificationDispatcher($channels, new NullLogger());
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
