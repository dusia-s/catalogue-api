<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\ProductSavedNotification;
use App\MessageHandler\ProductSavedNotificationHandler;
use App\Notification\Notification;
use App\Notification\NotificationChannelInterface;
use App\Notification\NotificationDispatcher;
use App\Notification\NotificationType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ProductSavedNotificationHandler::class)]
final class ProductSavedNotificationHandlerTest extends TestCase
{
    public function testItTurnsTheMessageIntoAProductSavedNotification(): void
    {
        $notification = $this->handle(
            new ProductSavedNotification(7, 'Desk Lamp', '89.50', ['ELEC', 'GARDEN']),
        );

        self::assertSame(NotificationType::ProductSaved, $notification->type);
        self::assertSame('Product saved: Desk Lamp', $notification->subject);
        self::assertStringContainsString('Product #7 "Desk Lamp"', $notification->message);
        self::assertStringContainsString('89.50', $notification->message);
        self::assertStringContainsString('ELEC, GARDEN', $notification->message);
    }

    public function testItCarriesAStructuredContextForChannelsThatCanUseIt(): void
    {
        $notification = $this->handle(
            new ProductSavedNotification(7, 'Desk Lamp', '89.50', ['ELEC', 'GARDEN']),
        );

        self::assertSame([
            'product_id' => 7,
            'product_name' => 'Desk Lamp',
            'price' => '89.50',
            'category_codes' => ['ELEC', 'GARDEN'],
        ], $notification->context);
    }

    /**
     * A product always has at least one category, but the message must not render a
     * dangling "with 0 category/categories: ." if that ever changes.
     */
    public function testItRendersAPlaceholderWhenThereAreNoCategoryCodes(): void
    {
        $notification = $this->handle(new ProductSavedNotification(7, 'Desk Lamp', '89.50', []));

        self::assertStringContainsString('0 category/categories: -.', $notification->message);
    }

    private function handle(ProductSavedNotification $message): Notification
    {
        $captured = null;

        $channel = $this->createStub(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(true);
        $channel->method('send')->willReturnCallback(
            static function (Notification $notification) use (&$captured): void {
                $captured = $notification;
            },
        );

        $handler = new ProductSavedNotificationHandler(
            new NotificationDispatcher([$channel], new NullLogger()),
        );
        $handler($message);

        self::assertInstanceOf(Notification::class, $captured);

        return $captured;
    }
}
