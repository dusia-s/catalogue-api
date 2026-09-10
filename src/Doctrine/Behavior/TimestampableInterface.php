<?php

declare(strict_types=1);

namespace App\Doctrine\Behavior;

/**
 * Implemented by entities whose creation/update timestamps are managed automatically.
 *
 * @see TimestampableTrait                              the default implementation
 * @see \App\Doctrine\TimestampableCollectionListener   keeps updatedAt honest for relation-only changes
 */
interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    /**
     * Mark the entity as modified now.
     */
    public function touch(): void;
}
