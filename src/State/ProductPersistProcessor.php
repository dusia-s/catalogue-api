<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Entity\Product;
use App\Message\ProductSavedNotification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Persists a product, then announces that it was saved.
 *
 * Wired per-operation on Product rather than as a decorator on
 * api_platform.doctrine.orm.state.persist_processor: that service backs every
 * resource, so decorating it would fire a product notification when a category
 * is created too.
 *
 * The message is dispatched after the inner processor has flushed, so the product
 * row and its join-table rows both exist and the transaction has committed. A
 * failing channel therefore cannot roll back the save.
 *
 * @implements ProcessorInterface<Product, Product>
 */
final class ProductPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Product, Product> $inner
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $inner,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $product = $this->inner->process($data, $operation, $uriVariables, $context);

        // Belt and braces: the processor is only attached to Product operations.
        if (!$product instanceof Product) {
            return $product;
        }

        $this->messageBus->dispatch(new ProductSavedNotification(
            productId: (int) $product->getId(),
            productName: (string) $product->getName(),
            price: (string) $product->getPrice(),
            categoryCodes: array_values(array_map(
                static fn (Category $category): string => (string) $category->getCode(),
                $product->getCategories()->toArray(),
            )),
        ));

        return $product;
    }
}
