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

use App\Entity\MediaMetadata;
use App\Enum\Source;
use Psr\Log\LoggerInterface;

class TvMetadataMerger
{
    public function __construct(
        private LoggerInterface $log,
    ) {
    }

    public function mergeTvMetadata(MediaMetadata $meta, ?array $tmdbFull, ?array $mazeFull): void
    {
        // TMDB primary
        if ($tmdbFull && isset($tmdbFull['show'])) {
            $show = $tmdbFull['show'];

            $meta->setTmdbId((string) ($show['id'] ?? ''));
            $meta->setImage($show['poster_path'] ?? $meta->getImage());
            $meta->setOverview($show['overview'] ?? $meta->getOverview());
            $meta->setRuntime($show['episode_run_time'][0] ?? $meta->getRuntime());
            $meta->setGenres($show['genres'] ?? $meta->getGenres());

            if (!$meta->getReleaseDate() && !empty($show['first_air_date'])) {
                $meta->setReleaseDate(new \DateTime($show['first_air_date']));
            }

            $this->log->debug('tv.merge.tmdb', [
                'tmdbId' => $meta->getTmdbId(),
            ]);
        }

        // TVMaze fallback
        if ($mazeFull && isset($mazeFull['show'])) {
            $show = $mazeFull['show'];

            $meta->setSource(Source::TVMAZE);

            if (!$meta->getImage()) {
                $meta->setImage($show['image']['original'] ?? $show['image']['medium'] ?? null);
            }

            if (!$meta->getOverview() && !empty($show['summary'])) {
                $meta->setOverview(strip_tags($show['summary']));
            }

            if (!$meta->getReleaseDate() && !empty($show['premiered'])) {
                $meta->setReleaseDate(new \DateTime($show['premiered']));
            }

            if (!$meta->getRuntime() && !empty($show['runtime'])) {
                $meta->setRuntime((int) $show['runtime']);
            }

            $meta->setExternalId((string) ($show['id'] ?? ''));

            $this->log->debug('tv.merge.maze', [
                'mazeId' => $meta->getExternalId(),
            ]);
        }
    }
}
