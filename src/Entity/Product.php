<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Doctrine\Behavior\TimestampableInterface;
use App\Doctrine\Behavior\TimestampableTrait;
use App\Repository\ProductRepository;
use App\State\ProductPersistProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        // The write operations run through ProductPersistProcessor, which persists
        // and then announces the save. Delete does not: the brief notifies on save.
        new Post(processor: ProductPersistProcessor::class),
        // PUT takes the whole representation, PATCH merges. PATCH requires
        // `Content-Type: application/merge-patch+json`; PUT is offered as well so a
        // plain `application/json` client is not stuck on a 415.
        //
        // standard_put is off so PUT populates the loaded entity instead of building
        // a replacement from the body. Under standard PUT the new instance has no
        // createdAt — it is read-only, so nothing in the payload can set it, and
        // #[ORM\PrePersist] does not run on an update — and the write fails on a NOT
        // NULL created_at. Populating keeps the creation date immutable, which is
        // what "date added" should mean anyway.
        new Put(
            processor: ProductPersistProcessor::class,
            extraProperties: ['standard_put' => false],
        ),
        new Patch(processor: ProductPersistProcessor::class),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    order: ['createdAt' => 'DESC'],
)]
class Product implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    // normalizer: trim, or a name of nothing but spaces counts as filled in.
    #[Assert\NotBlank(message: 'A product name is required.', normalizer: 'trim')]
    #[Assert\Length(max: 255)]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(example: 'Carbon Wheelset 45 mm')]
    private ?string $name = null;

    /**
     * Stored as DECIMAL and exposed as a string on purpose: binary floats cannot
     * represent every two-decimal money value exactly, and Doctrine maps DECIMAL
     * to a PHP string to preserve precision end to end.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'A product price is required.')]
    // Regex ignores an empty string, the way nearly every constraint but NotBlank and
    // NotNull does, so "" would otherwise sail through to the column. NotBlank special
    // cases "0", which stays a legitimate price.
    #[Assert\NotBlank(message: 'A product price is required.', normalizer: 'trim')]
    // The column is DECIMAL(10,2), so the format has to be pinned here rather than
    // left to the database. PositiveOrZero alone is not enough: it compares a string
    // against zero, so "abc" passes validation and then fails the INSERT with a 500,
    // an over-long value overflows the column the same way, and a third decimal place
    // is silently rounded away. Eight integer digits and two decimals is exactly what
    // DECIMAL(10,2) holds, and leading "-" is absent from the pattern by design.
    // The D modifier stops `$` from matching before a trailing newline, so "12.34\n"
    // is rejected rather than accepted. MySQL happens to trim it while casting to
    // DECIMAL, so nothing malformed was reaching the column, but the constraint should
    // not be relying on the database to clean up after it.
    #[Assert\Regex(
        pattern: '/^\d{1,8}(\.\d{1,2})?$/D',
        message: 'Price must be a non-negative amount with up to 8 digits and 2 decimal places, for example "89.50".',
    )]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(
        description: 'Decimal amount as a string, so no precision is lost in transit.',
        example: '4890.00',
    )]
    private ?string $price = null;

    /**
     * Owning side of the relation; the join table lives here.
     *
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_category')]
    #[Assert\Count(min: 1, minMessage: 'A product must belong to at least one category.')]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(
        description: 'Category IRIs, e.g. "/api/categories/1" - not the id or the code. '
            .'At least one is required; list them with GET /api/categories.',
        example: ['/api/categories/1', '/api/categories/2'],
    )]
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }
}
