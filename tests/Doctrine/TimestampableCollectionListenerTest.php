<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\TimestampableCollectionListener;
use App\Entity\Category;
use App\Entity\Product;
use App\Tests\Support\CategoryCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Doctrine builds a change set from an entity's own scalar fields, so a product whose
 * only change is its category collection stays "clean" and #[ORM\PreUpdate] never
 * fires. These tests pin the behaviour the brief requires: the date of update is
 * maintained automatically, including when only the category links move.
 *
 * updated_at is backdated with raw SQL rather than by waiting, so the assertions do
 * not depend on a second elapsing between two flushes.
 */
#[CoversClass(TimestampableCollectionListener::class)]
final class TimestampableCollectionListenerTest extends KernelTestCase
{
    private const BACKDATED = '2000-01-01 00:00:00';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAddingACategoryUpdatesTheProductTimestamp(): void
    {
        $product = $this->persistProduct($this->persistCategory());
        $extra = $this->persistCategory();

        $this->backdate($product);

        $product->addCategory($extra);
        $this->entityManager->flush();

        self::assertTrue(
            $product->getUpdatedAt() > new \DateTimeImmutable('2020-01-01'),
            'Adding a category should have refreshed updated_at.',
        );
    }

    public function testRemovingACategoryUpdatesTheProductTimestamp(): void
    {
        $first = $this->persistCategory();
        $product = $this->persistProduct($first, $this->persistCategory());

        $this->backdate($product);

        $product->removeCategory($first);
        $this->entityManager->flush();

        self::assertTrue(
            $product->getUpdatedAt() > new \DateTimeImmutable('2020-01-01'),
            'Removing a category should have refreshed updated_at.',
        );
    }

    public function testAnUntouchedProductKeepsItsTimestamp(): void
    {
        $product = $this->persistProduct($this->persistCategory());

        $this->backdate($product);

        $this->entityManager->flush();

        self::assertSame(
            self::BACKDATED,
            $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'A flush that changes nothing must not rewrite updated_at.',
        );
    }

    /**
     * Removing a product also schedules its category collection for deletion. The
     * listener has to skip owners already scheduled for delete, or Doctrine throws
     * "entity not managed" while recomputing their change set.
     */
    public function testDeletingAProductWithCategoriesDoesNotThrow(): void
    {
        $product = $this->persistProduct($this->persistCategory());

        // Doctrine clears the identifier on delete, so capture it while it exists.
        $id = $product->getId();

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        self::assertNull($this->entityManager->getRepository(Product::class)->find($id));
    }

    private function persistCategory(): Category
    {
        $category = (new Category())->setCode(CategoryCode::next());

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function persistProduct(Category ...$categories): Product
    {
        $product = new Product();
        $product->setName('Desk Lamp');
        $product->setPrice('89.50');

        foreach ($categories as $category) {
            $product->addCategory($category);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    /**
     * Push updated_at into the past without dirtying the entity, so the next flush
     * only sees whatever the test itself changed.
     */
    private function backdate(Product $product): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE product SET updated_at = :backdated WHERE id = :id',
            ['backdated' => self::BACKDATED, 'id' => $product->getId()],
        );

        // refresh() rather than clear(): the entity stays managed so the caller can
        // keep using this instance, and Doctrine's original data is reset, which is
        // what stops the next flush from seeing a stale change set.
        $this->entityManager->refresh($product);

        self::assertSame(self::BACKDATED, $product->getUpdatedAt()?->format('Y-m-d H:i:s'));
    }
}
