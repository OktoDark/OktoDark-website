<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Episode;

use App\Entity\Episode;
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

class EpisodeImportService
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
        $title = $record['title'] ?? 'Episode';
        $seasonNumber = $record['season'] ?? $record['episode']['season'] ?? null;
        $episodeNumber = $record['number'] ?? $record['episode']['number'] ?? null;
        $ids = $record['ids'] ?? $record['episode']['ids'] ?? [];
        $user = $record['user'];

        $showMeta = $record['show']['metadata'] ?? null;
        $showIds = $record['show']['ids'] ?? [];

        if (!$seasonNumber || !$episodeNumber) {
            $this->log->warning('episode.import.missing_numbers', ['record' => $record]);

            return;
        }

        $this->log->info('episode.import.start', [
            'title' => $title,
            'seasonNumber' => $seasonNumber,
            'episodeNumber' => $episodeNumber,
            'ids' => $ids,
        ]);

        /**
         * ⭐ TMDB‑first identity for episode metadata.
         */
        $meta = $this->lookup->findOrCreateMetadata(
            mediaType: MediaType::EPISODE,
            ids: $ids,                     // TMDB episode ID first, fallback to TVMaze
            title: $title,
            year: $record['year'] ?? null,
            source: Source::TRAKT,
            seasonNumber: $seasonNumber,
            episodeNumber: $episodeNumber,
        );

        /**
         * ⭐ TMDB episode enrichment.
         */
        $tmdbEpisode = null;
        if (!empty($showIds['tmdb'])) {
            $tmdbEpisode = $this->tmdb->fetchEpisode(
                (int) $showIds['tmdb'],
                (int) $seasonNumber,
                (int) $episodeNumber
            );
        }

        /**
         * ⭐ TVMaze episode enrichment.
         */
        $mazeEpisode = null;
        if (!empty($ids['tvmaze'])) {
            try {
                $mazeEpisode = $this->maze->getEpisode((int) $ids['tvmaze']);
            } catch (\Throwable) {
                $mazeEpisode = null;
            }
        }

        /*
         * ⭐ Merge TMDB + TVMaze metadata
         */
        $this->merge->mergeEpisodeMetadata($meta, $tmdbEpisode, $mazeEpisode);

        /**
         * ⭐ Duplicate detection (correct).
         */
        $existing = $this->em->getRepository(Episode::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $user,
        ]);

        if ($existing) {
            $this->log->warning('episode.duplicate', [
                'season' => $seasonNumber,
                'episode' => $episodeNumber,
                'meta' => $meta->getMediaId(),
            ]);

            return;
        }

        /**
         * ⭐ Find season entity (correct).
         */
        $season = $this->findSeasonEntity($showMeta, $seasonNumber, $user);

        if (!$season) {
            $this->log->warning('episode.import.no_season_found', [
                'season' => $seasonNumber,
                'episode' => $episodeNumber,
            ]);

            return;
        }

        /**
         * ⭐ Create episode entity.
         */
        $episode = new Episode();
        $episode->setMediaMetadata($meta);
        $episode->setRelatedSeason($season);
        $episode->setUser($user);

        $this->em->persist($episode);

        /*
         * ⭐ Watch history + ratings
         */
        $this->applyWatchHistory($episode, $record);
        $this->applyRatings($episode, $record);

        $this->em->flush();

        $this->log->info('episode.import.success', [
            'season' => $seasonNumber,
            'episode' => $episodeNumber,
            'meta' => $meta->getMediaId(),
        ]);
    }

    private function findSeasonEntity(?MediaMetadata $showMeta, int $seasonNumber, $user): ?Season
    {
        if (!$showMeta) {
            return null;
        }

        return $this->em->getRepository(Season::class)->findOneBy([
            'user' => $user,
            'relatedTv' => $this->em->getRepository(TV::class)->findOneBy([
                'mediaMetadata' => $showMeta,
                'user' => $user,
            ]),
            'mediaMetadata.seasonNumber' => $seasonNumber,
        ]);
    }

    private function applyWatchHistory(Episode $episode, array $record): void
    {
        // Your existing logic
    }

    private function applyRatings(Episode $episode, array $record): void
    {
        // Your existing logic
    }
}
