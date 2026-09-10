<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Entity\Product;
use App\Message\ProductSavedNotification;
use App\State\ProductPersistProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ProductPersistProcessor::class)]
final class ProductPersistProcessorTest extends TestCase
{
    public function testItPersistsThroughTheInnerProcessorThenAnnouncesTheSave(): void
    {
        $product = self::product();
        $operation = new Post();

        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())
            ->method('process')
            ->with($product, $operation, [], [])
            ->willReturn($product);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $result = (new ProductPersistProcessor($inner, $bus))->process($product, $operation);

        self::assertSame($product, $result);
    }

    public function testTheDispatchedMessageSnapshotsTheProductAndItsCategoryCodes(): void
    {
        $dispatched = null;

        $product = self::product();

        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn($product);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            },
        );

        (new ProductPersistProcessor($inner, $bus))->process($product, new Post());

        self::assertInstanceOf(ProductSavedNotification::class, $dispatched);
        self::assertSame(7, $dispatched->productId);
        self::assertSame('Desk Lamp', $dispatched->productName);
        self::assertSame('89.50', $dispatched->price);
        self::assertSame(['ELEC', 'GARDEN'], $dispatched->categoryCodes);
    }

    /**
     * The processor is only attached to Product operations, but it must not announce
     * a saved product if it ever sees something else.
     */
    public function testItAnnouncesNothingWhenTheInnerProcessorReturnsAnotherType(): void
    {
        $other = new Category();

        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn($other);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $result = (new ProductPersistProcessor($inner, $bus))->process($other, new Post());

        self::assertSame($other, $result);
    }

    private static function product(): Product
    {
        $product = new Product();
        $product->setName('Desk Lamp');
        $product->setPrice('89.50');
        $product->addCategory((new Category())->setCode('ELEC'));
        $product->addCategory((new Category())->setCode('GARDEN'));

        // The id is normally assigned by Doctrine during the inner processor's flush.
        (new \ReflectionProperty(Product::class, 'id'))->setValue($product, 7);

        return $product;
    }
}
