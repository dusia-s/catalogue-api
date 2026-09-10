<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed data so the API is not empty on a first run.
 *
 * Timestamps are left to the lifecycle callbacks rather than set here, which also
 * exercises them outside the HTTP layer. Notifications are deliberately not raised:
 * they belong to the save operation exposed by the API, not to seeding.
 */
final class AppFixtures extends Fixture
{
    /**
     * ELECTRONIC is exactly ten characters — the documented maximum for a code.
     */
    private const CATEGORY_CODES = ['ELECTRONIC', 'BOOKS', 'GARDEN', 'TOYS'];

    /**
     * @var list<array{string, string, list<string>}>
     */
    private const PRODUCTS = [
        ['Mechanical Keyboard', '249.99', ['ELECTRONIC']],
        ['Desk Lamp', '89.50', ['ELECTRONIC', 'GARDEN']],
        ['The Pragmatic Programmer', '164.00', ['BOOKS']],
        ['Wooden Puzzle', '39.90', ['TOYS', 'BOOKS']],
        ['Watering Can', '24.00', ['GARDEN']],
    ];

    public function load(ObjectManager $manager): void
    {
        $categories = [];

        foreach (self::CATEGORY_CODES as $code) {
            $categories[$code] = (new Category())->setCode($code);
            $manager->persist($categories[$code]);
        }

        foreach (self::PRODUCTS as [$name, $price, $codes]) {
            $product = new Product();
            $product->setName($name);
            $product->setPrice($price);

            foreach ($codes as $code) {
                $product->addCategory($categories[$code]);
            }

            $manager->persist($product);
        }

        $manager->flush();
    }
}
