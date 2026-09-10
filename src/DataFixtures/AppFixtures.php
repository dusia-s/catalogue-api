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
     * A bike shop inventory.
     *
     * DRIVETRAIN is exactly ten characters — the documented maximum for a code.
     */
    private const CATEGORY_CODES = [
        'BIKES',
        'FRAMES',
        'WHEELS',
        'DRIVETRAIN',
        'BRAKES',
        'APPAREL',
        'ACCESSORY',
        'TOOLS',
    ];

    /**
     * Several products sit in more than one category, so the seeded data exercises
     * the many-to-many relation rather than just filling the tables.
     *
     * @var list<array{string, string, list<string>}>
     */
    private const PRODUCTS = [
        ['Gravel Bike Explorer 2', '8499.00', ['BIKES']],
        ['Road Bike Velocita SL', '12750.00', ['BIKES']],
        ['Kids Bike Sprout 20 inch', '1299.00', ['BIKES']],
        ['Carbon Endurance Frameset', '5200.00', ['FRAMES']],
        ['Alloy Gravel Frameset', '2150.00', ['FRAMES']],
        ['Carbon Wheelset 45 mm', '4890.00', ['WHEELS']],
        ['Tubeless Gravel Tyre 40c', '229.00', ['WHEELS', 'ACCESSORY']],
        ['11-Speed Chain', '119.00', ['DRIVETRAIN']],
        ['Compact Crankset 50/34', '1340.00', ['DRIVETRAIN']],
        ['Hydraulic Disc Brake Set', '1580.00', ['BRAKES', 'DRIVETRAIN']],
        ['Sintered Brake Pads', '89.00', ['BRAKES', 'ACCESSORY']],
        ['Vented Road Helmet', '649.00', ['APPAREL']],
        ['Thermal Bib Tights', '429.00', ['APPAREL']],
        ['Torque Wrench 2-24 Nm', '520.00', ['TOOLS']],
        ['Tubeless Repair Kit', '75.00', ['TOOLS', 'ACCESSORY']],
        ['USB Light Set 800 lm', '310.00', ['ACCESSORY']],
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
