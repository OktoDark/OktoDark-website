<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv;

use App\Entity\Episode;
use App\Entity\MediaMetadata;
use App\Entity\Season;
use App\Entity\TV;
use App\Enum\MediaType;
use App\Service\Import\Metadata\MetadataHydrator;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class TvHierarchyBuilder
{
    public function __construct(
        private ManagerRegistry $registry,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private MetadataHydrator $hydrator,
        private LoggerInterface $log,
    ) {
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->registry->getManager();
    }

    public function build(TV $tv, MediaMetadata $tvMeta, ?array $tmdbFull, ?array $mazeFull, $user): void
    {
        $seasonMap = [];

        /*
         * ⭐ TMDB seasons first (authoritative)
         */
        if ($tmdbFull && isset($tmdbFull['seasons'])) {
            foreach ($tmdbFull['seasons'] as $seasonNumber => $seasonData) {
                $seasonMeta = $this->lookup->findOrCreateMetadata(
                    mediaType: MediaType::TV,
                    ids: ['tmdb' => $seasonData['season']['id'] ?? null],
                    title: $seasonData['season']['name'] ?? "{$tvMeta->getTitle()} S{$seasonNumber}",
                    year: null,
                    source: $tvMeta->getSource(),
                    seasonNumber: $seasonNumber,
                    episodeNumber: null,
                );

                $hydrated = $this->hydrator->hydrateTmdbSeason($seasonData['season']);
                $this->merge->mergeSeasonMetadata($seasonMeta, $hydrated, null);

                $seasonEntity = $this->findOrCreateSeasonEntity($seasonMeta, $tv, $user);
                $seasonMap[$seasonNumber] = $seasonEntity;

                $this->log->debug('tv.season.created_or_reused.tmdb', [
                    'season' => $seasonNumber,
                    'meta' => $seasonMeta->getMediaId(),
                ]);
            }
        }

        /*
         * ⭐ TVMaze seasons fallback
         */
        if ($mazeFull && isset($mazeFull['seasons'])) {
            foreach ($mazeFull['seasons'] as $mazeSeason) {
                $seasonNumber = (int) ($mazeSeason['number'] ?? 0);
                if ($seasonNumber <= 0 || isset($seasonMap[$seasonNumber])) {
                    continue;
                }

                $seasonMeta = $this->lookup->findOrCreateMetadata(
                    mediaType: MediaType::TV,
                    ids: ['tvmaze' => $mazeSeason['id'] ?? null],
                    title: $mazeSeason['name'] ?? "{$tvMeta->getTitle()} S{$seasonNumber}",
                    year: null,
                    source: $tvMeta->getSource(),
                    seasonNumber: $seasonNumber,
                    episodeNumber: null,
                );

                $this->merge->mergeSeasonMetadata($seasonMeta, null, $mazeSeason);

                $seasonEntity = $this->findOrCreateSeasonEntity($seasonMeta, $tv, $user);
                $seasonMap[$seasonNumber] = $seasonEntity;

                $this->log->debug('tv.season.created_or_reused.maze', [
                    'season' => $seasonNumber,
                    'meta' => $seasonMeta->getMediaId(),
                ]);
            }
        }

        /*
         * ⭐ TMDB episodes (authoritative)
         */
        if ($tmdbFull && isset($tmdbFull['seasons'])) {
            foreach ($tmdbFull['seasons'] as $seasonNumber => $seasonData) {
                foreach ($seasonData['episodes'] as $epData) {
                    $episodeNumber = (int) $epData['episode_number'];

                    $epMeta = $this->lookup->findOrCreateMetadata(
                        mediaType: MediaType::EPISODE,
                        ids: ['tmdb' => $epData['id']],
                        title: $epData['name'] ?? "{$tvMeta->getTitle()} S{$seasonNumber}E{$episodeNumber}",
                        year: null,
                        source: $tvMeta->getSource(),
                        seasonNumber: $seasonNumber,
                        episodeNumber: $episodeNumber,
                    );

                    $hydrated = $this->hydrator->hydrateTmdbEpisode($epData);
                    $this->merge->mergeEpisodeMetadata($epMeta, $hydrated, null);

                    $this->findOrCreateEpisodeEntity($epMeta, $seasonMap[$seasonNumber], $tv, $tvMeta, $user);

                    $this->log->debug('tv.episode.created_or_reused.tmdb', [
                        'season' => $seasonNumber,
                        'episode' => $episodeNumber,
                    ]);
                }
            }
        }

        /*
         * ⭐ TVMaze episodes fallback
         */
        if ($mazeFull && isset($mazeFull['episodes'])) {
            foreach ($mazeFull['episodes'] as $mazeEp) {
                $seasonNumber = (int) ($mazeEp['season'] ?? 0);
                $episodeNumber = (int) ($mazeEp['number'] ?? 0);

                if ($seasonNumber <= 0 || $episodeNumber <= 0) {
                    continue;
                }

                $epMeta = $this->lookup->findOrCreateMetadata(
                    mediaType: MediaType::EPISODE,
                    ids: ['tvmaze' => $mazeEp['id'] ?? null],
                    title: $mazeEp['name'] ?? "{$tvMeta->getTitle()} S{$seasonNumber}E{$episodeNumber}",
                    year: null,
                    source: $tvMeta->getSource(),
                    seasonNumber: $seasonNumber,
                    episodeNumber: $episodeNumber,
                );

                $this->merge->mergeEpisodeMetadata($epMeta, null, $mazeEp);

                $this->findOrCreateEpisodeEntity($epMeta, $seasonMap[$seasonNumber] ?? null, $tv, $tvMeta, $user);

                $this->log->debug('tv.episode.created_or_reused.maze', [
                    'season' => $seasonNumber,
                    'episode' => $episodeNumber,
                ]);
            }
        }
    }

    private function findOrCreateSeasonEntity(MediaMetadata $meta, TV $tv, $user): Season
    {
        $existing = $this->em()->getRepository(Season::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $user,
        ]);

        if ($existing) {
            return $existing;
        }

        $season = new Season();
        $season->setMediaMetadata($meta);
        $season->setRelatedTv($tv);
        $season->setUser($user);

        $this->em()->persist($season);

        return $season;
    }

    private function findOrCreateEpisodeEntity(MediaMetadata $meta, ?Season $season, TV $tv, MediaMetadata $tvMeta, $user): Episode
    {
        if (!$season) {
            // Auto-create missing season metadata
            $seasonMeta = $this->lookup->findOrCreateMetadata(
                mediaType: MediaType::TV,
                ids: [], // no provider ID available
                title: "{$tvMeta->getTitle()} S{$meta->getSeasonNumber()}",
                year: null,
                source: $tvMeta->getSource(),
                seasonNumber: $meta->getSeasonNumber(),
                episodeNumber: null,
            );

            $season = $this->findOrCreateSeasonEntity($seasonMeta, $tv, $user);
        }

        $existing = $this->em()->getRepository(Episode::class)->findOneBy([
            'mediaMetadata' => $meta,
            'user' => $user,
        ]);

        if ($existing) {
            return $existing;
        }

        $episode = new Episode();
        $episode->setMediaMetadata($meta);
        $episode->setRelatedSeason($season);
        $episode->setUser($user);

        $this->em()->persist($episode);

        return $episode;
    }
}
