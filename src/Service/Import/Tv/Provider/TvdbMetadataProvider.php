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
use App\Service\TvdbService;

/**
 * TheTVDB v4-backed implementation of the TV metadata provider.
 *
 * Acts as a secondary source behind TVMaze. The native TvdbService returns the
 * same TMDB-like shape via fetchFullShow(), which we normalize down to the
 * simpler per-episode contract consumed by the importer.
 */
class TvdbMetadataProvider implements TvMetadataProviderInterface
{
    private array $showCache = [];

    public function __construct(
        private TvdbService $tvdb,
    ) {
    }

    public function getSource(): Source
    {
        return Source::TVDB;
    }

    public function isConfigured(): bool
    {
        return $this->tvdb->isConfigured();
    }

    public function resolveShowId(string $title): ?string
    {
        $id = $this->tvdb->findShowId([], $title);
        if (null === $id) {
            return null;
        }

        $show = $this->fetchShow((string) $id);
        if (null === $show) {
            return null;
        }

        $meta = (new MediaMetadata())->setTitle($title);
        $this->applyShow($meta, $show);

        return $meta->getExternalId();
    }

    public function enrichShow(MediaMetadata $meta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($meta->getImage() && $meta->getOverview())) {
            return;
        }

        $showId = $ids['tvdb'] ?? $meta->getExternalId();
        if (null === $showId) {
            return;
        }

        $show = $this->fetchShow((string) $showId);
        if (null === $show) {
            return;
        }

        $this->applyShow($meta, $show);
        $meta->setExternalId((string) $show['show']['id']);
    }

    public function enrichSeason(MediaMetadata $showMeta, MediaMetadata $seasonMeta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($seasonMeta->getImage() && $seasonMeta->getOverview())) {
            return;
        }

        $showId = $ids['tvdb'] ?? $showMeta->getExternalId();
        if (null === $showId) {
            return;
        }

        $show = $this->fetchShow((string) $showId);
        if (null === $show) {
            return;
        }

        $seasonNumber = $seasonMeta->getSeasonNumber();
        $season = $show['seasons'][$seasonNumber]['season'] ?? null;
        if (null === $season) {
            return;
        }

        if (!empty($season['image'])) {
            $seasonMeta->setImage($season['image']);
        }
        if (!empty($season['overview'])) {
            $seasonMeta->setOverview($season['overview']);
        }
        if (!empty($season['air_date'])) {
            try {
                $seasonMeta->setReleaseDate(new \DateTime($season['air_date']));
            } catch (\Throwable) {
            }
        }
    }

    public function enrichEpisode(MediaMetadata $showMeta, MediaMetadata $epMeta, array $ids = [], bool $force = true): void
    {
        if (!$force && ($epMeta->getImage() && $epMeta->getOverview())) {
            return;
        }

        $showId = $ids['tvdb'] ?? $showMeta->getExternalId();
        if (null === $showId) {
            return;
        }

        $show = $this->fetchShow((string) $showId);
        if (null === $show) {
            return;
        }

        $seasonNumber = $epMeta->getSeasonNumber();
        $episodeNumber = $epMeta->getEpisodeNumber();

        $season = $show['seasons'][$seasonNumber]['episodes'] ?? [];
        foreach ($season as $ep) {
            if ((int) ($ep['episode_number'] ?? 0) !== $episodeNumber) {
                continue;
            }

            if (!empty($ep['image'])) {
                $epMeta->setImage($ep['image']);
            }
            if (!empty($ep['overview'])) {
                $epMeta->setOverview($ep['overview']);
            }
            if (!empty($ep['air_date'])) {
                try {
                    $epMeta->setReleaseDate(new \DateTime($ep['air_date']));
                } catch (\Throwable) {
                }
            }
            if (!empty($ep['runtime'])) {
                $epMeta->setRuntime((int) $ep['runtime']);
            }

            break;
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
                'image' => $ep['image'] ?? null,
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

        $full = $this->tvdb->fetchFullShow((int) $showId);
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
        $data = $show['show'];

        if (!empty($data['image'])) {
            $meta->setImage($data['image']);
        }
        if (!empty($data['overview'])) {
            $meta->setOverview($data['overview']);
        }
        if (!empty($data['genres'])) {
            $meta->setGenres(array_column($data['genres'], 'name'));
        }
        if (!empty($data['first_air_date'])) {
            try {
                $meta->setReleaseDate(new \DateTime($data['first_air_date']));
            } catch (\Throwable) {
            }
        }
        if (!empty($data['episode_run_time'][0])) {
            $meta->setRuntime((int) $data['episode_run_time'][0]);
        }
        if (!empty($data['origin_country'][0])) {
            $meta->setCountry($data['origin_country'][0]);
        }
    }
}
