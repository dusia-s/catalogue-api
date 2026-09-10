<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Doctrine\Behavior\TimestampableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Bumps updatedAt when only an entity's associations changed.
 *
 * Doctrine computes a change set from an entity's own scalar fields, so adding or
 * removing rows in a ManyToMany join table leaves the owning entity "clean" and
 * #[ORM\PreUpdate] never fires. Re-categorising a product would therefore keep a
 * stale "date updated", which the brief explicitly requires to be automatic.
 *
 * Handling this in onFlush rather than in the API layer keeps the guarantee at the
 * persistence boundary, so fixtures, commands and future callers get it too.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class TimestampableCollectionListener
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $collections = [
            ...$unitOfWork->getScheduledCollectionUpdates(),
            ...$unitOfWork->getScheduledCollectionDeletions(),
        ];

        foreach ($collections as $collection) {
            $owner = $collection->getOwner();

            if (!$owner instanceof TimestampableInterface) {
                continue;
            }

            // Removing an entity also schedules its collections for deletion. The owner
            // is gone, and recomputing a change set for it would throw "not managed".
            if ($unitOfWork->isScheduledForDelete($owner)) {
                continue;
            }

            $owner->touch();

            // Puts the entity into entityUpdates as well, which is what actually gets
            // the new timestamp written; touching the field alone is not enough here.
            $unitOfWork->recomputeSingleEntityChangeSet(
                $entityManager->getClassMetadata($owner::class),
                $owner,
            );
        }
    }
}
