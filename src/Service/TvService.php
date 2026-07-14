<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Domain\TvLifecycleManager;
use App\Entity\TV;
use App\Repository\TVRepository;

class TvService
{
    public function __construct(
        private readonly TVRepository $tvRepo,
        private readonly TvLifecycleManager $tvLifecycle,
        private readonly MetadataHydrator $metadataHydrator,
        private readonly TvHierarchyBuilder $hierarchyBuilder,
    ) {
    }

    /**
     * Recompute TV progress + status.
     */
    public function updateTv(TV $tv): void
    {
        $this->tvLifecycle->recomputeProgressAndStatus($tv);
    }

    /**
     * Explicitly mark TV completed.
     */
    public function markCompleted(TV $tv): void
    {
        $this->tvLifecycle->markCompleted($tv);
    }

    /**
     * Reset TV to planning.
     */
    public function reset(TV $tv): void
    {
        $this->tvLifecycle->resetToPlanning($tv);
    }

    /**
     * Refresh metadata using TMDB + TVMaze.
     */
    public function refreshMetadata(TV $tv): void
    {
        $meta = $tv->getMediaMetadata();
        $this->metadataHydrator->hydrate($meta);
    }

    /**
     * Rebuild seasons + episodes hierarchy.
     */
    public function rebuildHierarchy(TV $tv): void
    {
        $this->hierarchyBuilder->rebuild($tv);
        $this->tvLifecycle->recomputeProgressAndStatus($tv);
    }

    /**
     * Sync TV show with external sources (TMDB + TVMaze).
     */
    public function syncExternalSources(TV $tv): void
    {
        $this->refreshMetadata($tv);
        $this->rebuildHierarchy($tv);
    }
}
