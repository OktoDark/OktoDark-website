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
use App\Service\Import\Metadata\Structure\ShowFull;
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

    /**
     * Build the season/episode hierarchy from the multi-source discovery result.
     *
     * Episode source selection (roadmap Phase 4):
     *  - TVDB if available (best episode structure / numbering)
     *  - Else TMDB
     *  - Else TVMaze
     * Seasons/episodes are created from the best source, missing fields are
     * filled from TMDB/TVMaze, and images always come from TMDB.
     *
     * @param array{tmdb:?ShowFull, tvdb:?ShowFull, tvmaze:?ShowFull, best:string} $discovery
     */
    public function build(TV $tv, MediaMetadata $tvMeta, array $discovery, $user): void
    {
        $tmdb = $discovery['tmdb'] ?? null;
        $tvdb = $discovery['tvdb'] ?? null;
        $maze = $discovery['tvmaze'] ?? null;

        // Raw TMDB-shaped arrays consumed by merge/hydrator.
        $tmdbFull = $tmdb?->raw();
        $mazeFull = $maze?->raw();

        // Best source provides the canonical season set.
        $best = $discovery['best'] ?? 'tmdb';
        $bestShow = $discovery[$best] ?? $tmdb ?? $tvdb ?? $maze;

        $seasonMap = [];

        /*
         * ⭐ Seasons from best source
         */
        if ($bestShow) {
            foreach ($bestShow->seasons as $seasonNumber => $season) {
                $seasonData = $tmdbFull['seasons'][$seasonNumber] ?? null;
                $seasonMeta = $this->lookup->findOrCreateMetadata(
                    mediaType: MediaType::TV,
                    ids: ['tmdb' => $seasonData['season']['id'] ?? null],
                    title: $season->title ?? "{$tvMeta->getTitle()} S{$seasonNumber}",
                    year: null,
                    source: $tvMeta->getSource(),
                    seasonNumber: $seasonNumber,
                    episodeNumber: null,
                );

                $hydrated = $this->hydrator->hydrateTmdbSeason($seasonData['season'] ?? []);
                $this->merge->mergeSeasonMetadata($seasonMeta, $hydrated, null);

                $seasonMap[$seasonNumber] = $this->findOrCreateSeasonEntity($seasonMeta, $tv, $user);

                $this->log->debug('tv.season.created_or_reused', [
                    'season' => $seasonNumber,
                    'meta' => $seasonMeta->getMediaId(),
                ]);
            }
        }

        /*
         * ⭐ Episodes — TVDB first, then TMDB, then TVMaze
         */
        $episodeSource = $tvdb ?? $tmdb ?? $maze;
        if ($episodeSource) {
            foreach ($episodeSource->seasons as $seasonNumber => $season) {
                foreach ($season->episodes as $ep) {
                    $episodeNumber = $ep->episodeNumber;
                    if (null === $episodeNumber || $episodeNumber <= 0) {
                        continue;
                    }

                    $epData = ($tmdbFull['seasons'][$seasonNumber]['episodes'] ?? [])[$episodeNumber - 1] ?? null;
                    $epMeta = $this->lookup->findOrCreateMetadata(
                        mediaType: MediaType::EPISODE,
                        ids: ['tmdb' => $epData['id'] ?? null],
                        title: $ep->title ?? "{$tvMeta->getTitle()} S{$seasonNumber}E{$episodeNumber}",
                        year: null,
                        source: $tvMeta->getSource(),
                        seasonNumber: $seasonNumber,
                        episodeNumber: $episodeNumber,
                    );

                    $hydrated = $this->hydrator->hydrateTmdbEpisode($epData ?? []);
                    $this->merge->mergeEpisodeMetadata($epMeta, $hydrated, null);

                    $seasonEntity = $seasonMap[$seasonNumber] ?? null;
                    $this->findOrCreateEpisodeEntity($epMeta, $seasonEntity, $tv, $tvMeta, $user);

                    $this->log->debug('tv.episode.created_or_reused', [
                        'season' => $seasonNumber,
                        'episode' => $episodeNumber,
                    ]);
                }
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
