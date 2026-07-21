<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv\Provider;

use App\Entity\MediaMetadata;
use App\Enum\Source;
use App\Service\TmdbService;

/**
 * TMDB-backed implementation of the TV metadata provider.
 *
 * Acts as a tertiary source behind TVMaze and TheTVDB. TMDB returns richer
 * artwork (still images, trailers), so it is a good last-resort fallback when
 * neither TVMaze nor TVDB can resolve a title.
 */
class TmdbMetadataProvider implements TvMetadataProviderInterface
{
    private array $showCache = [];

    public function __construct(
        private TmdbService $tmdb,
    ) {
    }

    public function getSource(): Source
    {
        return Source::TMDB;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function resolveShowId(string $title): ?string
    {
        $show = $this->tmdb->searchShow($title, null);
        if (null === $show) {
            return null;
        }

        $id = (string) ($show['id'] ?? '');
        if ('' === $id) {
            return null;
        }

        $meta = (new MediaMetadata())->setTitle($title);
        $meta->setTmdbId($id);
        $this->applyShow($meta, $show);

        return $id;
    }

    public function enrichShow(MediaMetadata $meta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($meta->getImage() && $meta->getOverview())) {
            return;
        }

        $showId = $ids['tmdb'] ?? $meta->getTmdbId();
        if (null === $showId || '' === $showId) {
            return;
        }

        $show = $this->tmdb->getShow((int) $showId);
        if (null === $show) {
            return;
        }

        $meta->setTmdbId((string) $showId);
        $this->applyShow($meta, $show);
    }

    public function enrichSeason(MediaMetadata $showMeta, MediaMetadata $seasonMeta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($seasonMeta->getImage() && $seasonMeta->getOverview())) {
            return;
        }

        $showId = $ids['tmdb'] ?? $showMeta->getTmdbId();
        $seasonNumber = $seasonMeta->getSeasonNumber();
        if (null === $showId || '' === $showId || null === $seasonNumber) {
            return;
        }

        $season = $this->tmdb->findSeason((int) $showId, $seasonNumber);
        if (null === $season) {
            return;
        }

        $data = $season['metadata'];
        if (!empty($data['image'])) {
            $seasonMeta->setImage($data['image']);
        }
        if (!empty($data['overview'])) {
            $seasonMeta->setOverview($data['overview']);
        }
        if (!empty($data['releaseDate'])) {
            try {
                $seasonMeta->setReleaseDate(new \DateTime($data['releaseDate']));
            } catch (\Throwable) {
            }
        }
    }

    public function enrichEpisode(MediaMetadata $showMeta, MediaMetadata $epMeta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($epMeta->getImage() && $epMeta->getOverview())) {
            return;
        }

        $showId = $ids['tmdb'] ?? $showMeta->getTmdbId();
        $seasonNumber = $epMeta->getSeasonNumber();
        $episodeNumber = $epMeta->getEpisodeNumber();
        if (null === $showId || '' === $showId || null === $seasonNumber || null === $episodeNumber) {
            return;
        }

        $ep = $this->tmdb->findEpisode((int) $showId, $seasonNumber, $episodeNumber);
        if (null === $ep) {
            return;
        }

        if (!empty($ep['image'])) {
            $epMeta->setImage($ep['image']);
        }
        if (!empty($ep['overview'])) {
            $epMeta->setOverview($ep['overview']);
        }
        if (!empty($ep['releaseDate'])) {
            try {
                $epMeta->setReleaseDate(new \DateTime($ep['releaseDate']));
            } catch (\Throwable) {
            }
        }
        if (!empty($ep['runtime'])) {
            $epMeta->setRuntime((int) $ep['runtime']);
        }
        if (!empty($ep['cast'])) {
            $epMeta->setCast($ep['cast']);
        }
        if (!empty($ep['trailer'])) {
            $epMeta->setTrailer($ep['trailer']);
        }
    }

    public function getSeasonEpisodes(string $showId, int $seasonNumber): array
    {
        $show = $this->fetchShow($showId);
        if (null === $show) {
            return [];
        }

        $episodes = [];
        foreach ($show['seasons'][$seasonNumber]['episodes'] ?? [] as $ep) {
            $number = (int) ($ep['episode_number'] ?? 0);
            if ($number <= 0) {
                continue;
            }

            $episodes[] = [
                'season' => $seasonNumber,
                'number' => $number,
                'title' => $ep['name'] ?? null,
                'airdate' => $ep['air_date'] ?? null,
                'image' => isset($ep['still_path'])
                    ? 'https://image.tmdb.org/t/p/w500'.$ep['still_path']
                    : null,
                'overview' => $ep['overview'] ?? null,
                'runtime' => $ep['runtime'] ?? null,
            ];
        }

        return $episodes;
    }

    /**
     * Fetch & cache the normalized full show (show + seasons + episodes).
     *
     * @return array{show:array, seasons:array}|null
     */
    private function fetchShow(string $showId): ?array
    {
        if (isset($this->showCache[$showId])) {
            return $this->showCache[$showId];
        }

        $full = $this->tmdb->fetchFullShow((int) $showId);
        $result = null;

        if (null !== $full) {
            $result = [
                'show' => $full['show'],
                'seasons' => $full['seasons'],
            ];
        }

        $this->showCache[$showId] = $result;

        return $result;
    }

    private function applyShow(MediaMetadata $meta, array $show): void
    {
        $data = $this->tmdb->hydrateMetadata($show);

        if (!empty($data['image'])) {
            $meta->setImage($data['image']);
        }
        if (!empty($data['overview'])) {
            $meta->setOverview($data['overview']);
        }
        if (!empty($data['genres'])) {
            $meta->setGenres($data['genres']);
        }
        if (!empty($data['releaseDate'])) {
            try {
                $meta->setReleaseDate(new \DateTime($data['releaseDate']));
            } catch (\Throwable) {
            }
        }
        if (!empty($data['runtime'])) {
            $meta->setRuntime((int) $data['runtime']);
        }
        if (!empty($data['country'])) {
            $meta->setCountry($data['country']);
        }
        if (!empty($data['originalTitle'])) {
            $meta->setOriginalTitle($data['originalTitle']);
        }
    }
}
