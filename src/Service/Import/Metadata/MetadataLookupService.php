<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata;

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class MetadataLookupService
{
    public function __construct(
        private ManagerRegistry $registry,
        private LoggerInterface $log,
    ) {
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->registry->getManager();
    }

    public function findOrCreateMetadata(
        MediaType $mediaType,
        array $ids,
        string $title,
        ?int $year,
        Source $source,
        ?int $seasonNumber = null,
        ?int $episodeNumber = null,
    ): MediaMetadata {
        $repo = $this->em()->getRepository(MediaMetadata::class);

        // TMDB
        if (!empty($ids['tmdb'])) {
            $meta = $repo->findOneBy([
                'tmdbId' => (string) $ids['tmdb'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // IMDb
        if (!empty($ids['imdb'])) {
            $meta = $repo->findOneBy([
                'externalId' => (string) $ids['imdb'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // TVMaze
        if (!empty($ids['tvmaze'])) {
            $meta = $repo->findOneBy([
                'externalId' => (string) $ids['tvmaze'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // Alpha ID (used by movie providers)
        if (!empty($ids['alpha'])) {
            $meta = $repo->findOneBy([
                'mediaId' => (string) $ids['alpha'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // mediaId fallback (rare)
        if (!empty($ids['mediaId'])) {
            $meta = $repo->findOneBy([
                'mediaId' => (string) $ids['mediaId'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // Create new metadata
        $meta = new MediaMetadata();

        // Always assign a normalized mediaId
        $meta->setMediaId(
            mb_strtolower(
                mb_trim(
                    $ids['tmdb']
                    ?? $ids['imdb']
                    ?? $ids['alpha']
                    ?? $ids['slug']
                    ?? $ids['custom']
                    ?? bin2hex(random_bytes(16))
                )
            )
        );

        $meta->setMediaType($mediaType);
        $meta->setTitle($title);
        $meta->setSource($source);

        if ($year) {
            $meta->setYear($year);
        }

        if (null !== $seasonNumber) {
            $meta->setSeasonNumber($seasonNumber);
        }

        if (null !== $episodeNumber) {
            $meta->setEpisodeNumber($episodeNumber);
        }

        if (!empty($ids['tmdb'])) {
            $meta->setTmdbId((string) $ids['tmdb']);
        }

        if (!empty($ids['imdb'])) {
            $meta->setExternalId((string) $ids['imdb']);
        }

        if (!empty($ids['tvmaze'])) {
            $meta->setExternalId((string) $ids['tvmaze']);
        }

        $this->em()->persist($meta);

        return $meta;
    }
}
