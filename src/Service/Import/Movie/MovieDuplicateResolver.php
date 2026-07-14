<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie;

use App\Entity\MediaMetadata;
use App\Entity\Movie;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class MovieDuplicateResolver
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Resolve duplicates across providers:
     * - TMDB
     * - IMDb
     * - TVTime alpha key
     * - Fallback: metadata mediaId
     */
    public function resolve(MediaMetadata $meta, array $ids, User $user): ?Movie
    {
        $repo = $this->em->getRepository(Movie::class);

        // 1. TMDB match
        if (!empty($ids['tmdb'])) {
            $existing = $repo->findByMediaIdAndUser(strtolower((string) $ids['tmdb']), $user);
            if ($existing) {
                return $existing;
            }
        }

        // 2. IMDb match
        if (!empty($ids['imdb'])) {
            $existing = $repo->findByMediaIdAndUser(strtolower((string) $ids['imdb']), $user);
            if ($existing) {
                return $existing;
            }
        }

        // 3. TVTime alpha key match
        if (!empty($ids['alpha'])) {
            $existing = $repo->findByMediaIdAndUser(strtolower((string) $ids['alpha']), $user);
            if ($existing) {
                return $existing;
            }
        }

        // 4. Fallback: metadata mediaId
        return $repo->findByMediaIdAndUser(strtolower($meta->getMediaId()), $user);
    }
}
