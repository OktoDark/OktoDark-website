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

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;
use Doctrine\ORM\EntityManagerInterface;

class MetadataEnricher
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TmdbService $tmdb,
        private readonly TvMazeService $maze,
        private readonly AnilistService $anilist,
    ) {
    }

    /**
     * Enrich missing metadata (images, titles, etc.).
     */
    public function enrichMissing(): array
    {
        $updated = ['tv' => 0, 'seasons' => 0, 'episodes' => 0];

        $tvMetas = $this->em->getRepository(MediaMetadata::class)
            ->createQueryBuilder('m')
            ->where('m.mediaType = :type')
            ->andWhere('m.source = :source')
            ->andWhere('(m.image IS NULL OR m.image = \'\')')
            ->setParameter('type', MediaType::TV)
            ->setParameter('source', Source::MANUAL)
            ->getQuery()
            ->getResult();

        foreach ($tvMetas as $meta) {
            // Search via TvMaze
            $results = $this->maze->searchShows($meta->getTitle());
            if (!$results) {
                continue;
            }

            $mazeId = $results[0]['metaId'];
            $mazeShow = $this->maze->getShow($mazeId);

            if (!$mazeShow) {
                continue;
            }

            // Hydrate TV metadata
            $meta->applyImportedMetadata($mazeShow);
            $meta->setSource(Source::TVMAZE);

            // Hydrate show-level cast
            $cast = array_map(
                static fn (array $c) => [
                    'name' => $c['person']['name'] ?? null,
                    'character' => $c['character']['name'] ?? null,
                    'image' => $c['person']['image']['medium'] ?? null,
                ],
                $this->maze->getCast($mazeId) ?? []
            );
            if (!empty($cast)) {
                $meta->setCast($cast);
            }

            ++$updated['tv'];

            // Hydrate seasons
            $updated['seasons'] += $this->hydrateSeasons($meta, $mazeId);

            // Hydrate episodes
            $updated['episodes'] += $this->hydrateEpisodes($meta, $mazeId);
        }

        $this->em->flush();

        return $updated;
    }

    private function hydrateSeasons(MediaMetadata $tvMeta, int $mazeId): int
    {
        $seasons = $this->maze->getSeasons($mazeId);
        $count = 0;

        foreach ($seasons as $seasonData) {
            if (($seasonData['seasonNumber'] ?? 0) === 0) {
                continue;
            }

            $seasonMeta = $this->em->getRepository(MediaMetadata::class)->findOneBy([
                'mediaId' => $tvMeta->getMediaId(),
                'mediaType' => MediaType::SEASON,
                'seasonNumber' => $seasonData['seasonNumber'],
            ]);

            if (!$seasonMeta || $seasonMeta->getImage()) {
                continue;
            }

            $seasonMeta->applyImportedMetadata($seasonData);
            $seasonMeta->setSource(Source::TVMAZE);

            ++$count;
        }

        return $count;
    }

    private function hydrateEpisodes(MediaMetadata $tvMeta, int $mazeId): int
    {
        $episodes = $this->maze->getEpisodes($mazeId);
        $count = 0;

        foreach ($episodes as $epData) {
            if (($epData['seasonNumber'] ?? 0) === 0) {
                continue;
            }

            $epMeta = $this->em->getRepository(MediaMetadata::class)->findOneBy([
                'mediaId' => $tvMeta->getMediaId(),
                'mediaType' => MediaType::EPISODE,
                'seasonNumber' => $epData['seasonNumber'],
                'episodeNumber' => $epData['episodeNumber'],
            ]);

            if (!$epMeta || $epMeta->getTitle()) {
                continue;
            }

            $epMeta->applyImportedMetadata($epData);
            $epMeta->setSource(Source::TVMAZE);

            ++$count;
        }

        return $count;
    }

    // ---------------------------------------------------------
    // TMDB IMPORTERS (unchanged but updated to unified metadata)
    // ---------------------------------------------------------

    public function importMovie(string $tmdbId): MediaMetadata
    {
        $data = $this->tmdb->getMovie((int) $tmdbId);
        if (!$data) {
            throw new \RuntimeException("TMDB movie {$tmdbId} not found.");
        }

        $meta = new MediaMetadata();
        $meta->applyImportedMetadata($this->tmdb->hydrateMetadata($data));
        $meta->setMediaType(MediaType::MOVIE);
        $meta->setSource(Source::TMDB);

        $this->em->persist($meta);
        $this->em->flush();

        return $meta;
    }

    public function importAnime(string $anilistId): MediaMetadata
    {
        $data = $this->anilist->fetchAnime($anilistId);
        if (!$data) {
            throw new \RuntimeException("Anilist anime {$anilistId} not found.");
        }

        $meta = new MediaMetadata();
        $meta->applyImportedMetadata($data);
        $meta->setMediaType(MediaType::ANIME);
        $meta->setSource(Source::ANILIST);

        $this->em->persist($meta);
        $this->em->flush();

        return $meta;
    }

    public function importGame(string $igdbId): MediaMetadata
    {
        $data = $this->fetchIgdbGame($igdbId);
        if (!$data) {
            throw new \RuntimeException("IGDB game {$igdbId} not found.");
        }

        $meta = new MediaMetadata();
        $meta->applyImportedMetadata($data);
        $meta->setMediaType(MediaType::GAME);
        $meta->setSource(Source::IGDB);

        $this->em->persist($meta);
        $this->em->flush();

        return $meta;
    }
}
