<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Doctrine\Behavior\TimestampableInterface;
use App\Doctrine\Behavior\TimestampableTrait;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'A category with code "{{ value }}" already exists.')]
#[ApiResource(
    // The brief scopes create/update/delete to products; categories only need to
    // exist so products can reference them. Delete is deliberately absent: a product
    // must keep at least one category, and that invariant lives in validation, which
    // a cascading category delete would bypass and leave orphaned products behind.
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
    ],
    normalizationContext: ['groups' => ['category:read']],
    denormalizationContext: ['groups' => ['category:write']],
    order: ['code' => 'ASC'],
)]
class Category implements TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'product:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 10, unique: true)]
    // normalizer: trim, or a code of nothing but spaces counts as filled in.
    #[Assert\NotBlank(message: 'A category code is required.', normalizer: 'trim')]
    #[Assert\Length(max: 10, maxMessage: 'A category code cannot exceed {{ limit }} characters.')]
    // Without a character restriction, " BIKES " and a code containing a newline are
    // both accepted and stored verbatim, which makes a supposedly stable identifier
    // depend on invisible whitespace.
    // The D modifier matters: without it PCRE lets `$` match just before a trailing
    // newline, so "BIKES\n" satisfies the pattern and is stored with the newline
    // intact. Categories have no delete operation, which would make such a row
    // permanent.
    #[Assert\Regex(
        pattern: '/^[A-Za-z0-9_-]+$/D',
        message: 'A category code may contain only letters, digits, hyphens and underscores.',
    )]
    #[Groups(['category:read', 'category:write', 'product:read'])]
    #[ApiProperty(
        description: 'Unique identifier for the category, at most 10 characters.',
        example: 'WHEELS',
    )]
    private ?string $code = null;

    /**
     * Inverse side: Product owns the join table.
     *
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'categories')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }
}
