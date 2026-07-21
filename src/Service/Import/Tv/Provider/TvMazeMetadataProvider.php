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
use App\Service\TVMazeClient;

/**
 * TVMaze-backed implementation of the TV metadata provider.
 *
 * Wraps the existing {@see TVMazeClient} so the importer can treat TVMaze as
 * just one of several interchangeable metadata sources.
 */
class TvMazeMetadataProvider implements TvMetadataProviderInterface
{
    public function __construct(
        private TVMazeClient $client,
    ) {
    }

    public function getSource(): Source
    {
        return Source::TVMAZE;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function resolveShowId(string $title): ?string
    {
        $meta = (new MediaMetadata())
            ->setTitle($title);

        $this->client->enrichShowMetadata($meta, true);

        $id = $meta->getExternalId();

        return \is_string($id) && '' !== $id ? $id : null;
    }

    public function enrichShow(MediaMetadata $meta, array $ids = [], bool $force = true): void
    {
        if (empty($ids['tvmaze']) && null === $meta->getExternalId()) {
            $this->client->enrichShowMetadata($meta, $force);
        } elseif (!empty($ids['tvmaze'])) {
            $meta->setExternalId($ids['tvmaze']);
            $this->client->enrichShowMetadata($meta, $force);
        } else {
            $this->client->enrichShowMetadata($meta, $force);
        }
    }

    public function enrichSeason(MediaMetadata $showMeta, MediaMetadata $seasonMeta, array $ids = [], bool $force = true): void
    {
        $this->client->enrichSeasonMetadata($showMeta, $seasonMeta, $force);
    }

    public function enrichEpisode(MediaMetadata $showMeta, MediaMetadata $epMeta, array $ids = [], bool $force = true): void
    {
        $this->client->enrichEpisodeMetadata($showMeta, $epMeta, $force);
    }

    public function getSeasonEpisodes(string $showId, int $seasonNumber): array
    {
        $all = $this->client->getAllEpisodes($showId);

        $episodes = [];
        foreach ($all as $ep) {
            if ((int) ($ep['season'] ?? 0) !== $seasonNumber) {
                continue;
            }

            $number = (int) ($ep['number'] ?? 0);
            if ($number <= 0) {
                continue;
            }

            $image = $ep['image']['original'] ?? $ep['image']['medium'] ?? null;

            $episodes[] = [
                'season' => $seasonNumber,
                'number' => $number,
                'title' => $ep['name'] ?? null,
                'airdate' => $ep['airdate'] ?? null,
                'image' => $image,
                'overview' => isset($ep['summary']) ? strip_tags($ep['summary']) : null,
                'runtime' => $ep['runtime'] ?? null,
            ];
        }

        return $episodes;
    }
}
