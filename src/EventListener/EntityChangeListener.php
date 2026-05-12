<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\EventListener;

use App\Entity\ActivityLog;
use App\Entity\Board;
use App\Entity\Bug;
use App\Entity\Card;
use App\Entity\CardComment;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class EntityChangeListener
{
    /**
     * Automatically log entity creation.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        if ($this->shouldLogEntity($entity)) {
            $log = new ActivityLog();
            $log->setAction(ActivityLog::ACTION_CREATE);
            $log->setEntityType($this->getEntityType($entity));
            $log->setEntityId($entity->getId());
            $log->setDescription("Created {$this->getEntityType($entity)}: ".$this->getEntityDescription($entity));
            $log->setUser($this->getUserFromEntity($entity));

            $em->persist($log);
            $em->flush();
        }
    }

    /**
     * Automatically log entity updates.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        if ($this->shouldLogEntity($entity)) {
            $changes = $uow->getEntityChangeSet($entity);

            // Filter out internal fields we don't need to log
            $filteredChanges = [];
            foreach ($changes as $field => $values) {
                if (!in_array($field, ['updatedAt', 'createdAt'])) {
                    $filteredChanges[$field] = [
                        'old' => $values[0],
                        'new' => $values[1],
                    ];
                }
            }

            if (!empty($filteredChanges)) {
                $log = new ActivityLog();
                $log->setAction(ActivityLog::ACTION_UPDATE);
                $log->setEntityType($this->getEntityType($entity));
                $log->setEntityId($entity->getId());
                $log->setDescription("Updated {$this->getEntityType($entity)}: ".$this->getEntityDescription($entity));
                $log->setChanges($filteredChanges);
                $log->setUser($this->getUserFromEntity($entity));

                $em->persist($log);
                $em->flush();
            }
        }
    }

    /**
     * Automatically log entity deletion.
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();

        if ($this->shouldLogEntity($entity)) {
            $log = new ActivityLog();
            $log->setAction(ActivityLog::ACTION_DELETE);
            $log->setEntityType($this->getEntityType($entity));
            $log->setEntityId($entity->getId());
            $log->setDescription("Deleted {$this->getEntityType($entity)}: ".$this->getEntityDescription($entity));
            $log->setUser($this->getUserFromEntity($entity));

            $em->persist($log);
            $em->flush();
        }
    }

    private function shouldLogEntity(object $entity): bool
    {
        return $entity instanceof Board
            || $entity instanceof Card
            || $entity instanceof Bug
            || $entity instanceof CardComment;
    }

    private function getEntityType(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private function getEntityDescription(object $entity): string
    {
        if ($entity instanceof Board) {
            return $entity->getTitle();
        } elseif ($entity instanceof Card) {
            return $entity->getTitle();
        } elseif ($entity instanceof Bug) {
            return $entity->getTitle();
        } elseif ($entity instanceof CardComment) {
            return substr($entity->getContent(), 0, 50).'...';
        }

        return 'Unknown';
    }

    private function getUserFromEntity(object $entity): ?object
    {
        if ($entity instanceof Board) {
            return $entity->getOwner();
        } elseif ($entity instanceof Card) {
            return $entity->getCreatedBy();
        } elseif ($entity instanceof Bug) {
            return $entity->getReporter();
        } elseif ($entity instanceof CardComment) {
            return $entity->getAuthor();
        }

        return null;
    }
}
