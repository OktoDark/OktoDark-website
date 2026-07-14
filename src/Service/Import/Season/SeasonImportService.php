<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Season;

use App\Entity\Season;
use App\Entity\TV;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Service\Import\Metadata\MetadataHydrator;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use App\Service\TmdbService;
use App\Service\TvMazeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SeasonImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TmdbService $tmdb,
        private TvMazeService $maze,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private MetadataHydrator $hydrator,
        private LoggerInterface $log,
    ) {
    }

    public function import(array $record): void
    {
        $title = $record['title'] ?? 'Season';
        $seasonNumber = $record['season'] ?? $record['number'] ?? null;
        $ids = $record['ids'] ?? [];
        $user = $record['user'];
        $showMeta = $record['show']['metadata'] ?? null;
        $showIds = $record['show']['ids'] ?? [];

        if (!$seasonNumber) {
            $this->log->warning('season.import.missing_number', ['record' => $record]);

            return;
        }

        $this->log->info('season.import.start', [
            'title' => $title,
            'seasonNumber' => $seasonNumber,
            'ids' => $ids,
        ]);

        /**
         * ⭐ TMDB‑first identity for season metadata
         * Season metadata is MediaType::TV with seasonNumber set.
         */
        $meta = $this->lookup->findOrCreateMetadata(
            mediaType: MediaType::TV,
            ids: $ids,                     // TMDB season ID first, fallback to TVMaze
            title: $title,
            year: $record['year'] ?? null,
            source: Source::TRAKT,
            seasonNumber: $seasonNumber,
            episodeNumber: null,
        );

        /**
         * ⭐ TMDB season enrichment.
         */
        $tmdbSeason = null;
        if (!empty($showIds['tmdb'])) {
            $tmdbSeason = $this->tmdb->fetchSeason(
                (int) $showIds['tmdb'],
                (int) $seasonNumber
            );
        }

        /**
         * ⭐ TVMaze season enrichment.
         */
        $mazeSeason = null;
        if (!empty($ids['tvmaze'])) {
            try {
                $mazeSeason = $this->maze->getSeason((int) $ids['tvmaze']);
            } catch (\Throwable) {
                $mazeSeason = null;
            }
        }

        /*
         * ⭐ Merge TMDB + TVMaze metadata
         * TMDB wins, TVMaze fills gaps.
         */
        $this->merge->mergeSeasonMetadata($meta, $tmdbSeason, $mazeSeason);

        /**
         * ⭐ Duplicate detection (correct)
         * Because metadata is reused, this works perfectly.
         */
        $existing = $this->em->getRepository(Season::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $user,
        ]);

        if ($existing) {
            $this->log->warning('season.duplicate', [
                'season' => $seasonNumber,
                'meta' => $meta->getMediaId(),
            ]);

            return;
        }

        /**
         * ⭐ Create season entity.
         */
        $season = new Season();
        $season->setMediaMetadata($meta);
        $season->setUser($user);

        /*
         * ⭐ Link to TV show (correct)
         */
        if ($showMeta) {
            $tv = $this->em->getRepository(TV::class)->findOneBy([
                'mediaMetadata' => $showMeta,
                'user' => $user,
            ]);

            if ($tv) {
                $season->setRelatedTv($tv);
            }
        }

        $this->em->persist($season);

        /*
         * ⭐ Watch history + ratings
         */
        $this->applyWatchHistory($season, $record);
        $this->applyRatings($season, $record);

        $this->em->flush();

        $this->log->info('season.import.success', [
            'season' => $seasonNumber,
            'meta' => $meta->getMediaId(),
        ]);
    }

    private function applyWatchHistory(Season $season, array $record): void
    {
        // Your existing logic
    }

    private function applyRatings(Season $season, array $record): void
    {
        // Your existing logic
    }
}
