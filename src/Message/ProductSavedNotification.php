<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Raised once a product and its category links are committed.
 *
 * Carries a flat snapshot rather than the Product entity on purpose: the message
 * has to survive serialisation for the day this is routed to a real async
 * transport, and a detached entity would not.
 */
final readonly class ProductSavedNotification
{
    /**
     * @param list<string> $categoryCodes
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public string $price,
        public array $categoryCodes,
    ) {
    }
}
