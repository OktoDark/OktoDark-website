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
use App\Enum\Source;
use App\Service\Import\Metadata\Structure\ShowFull;
use Psr\Log\LoggerInterface;

class MetadataMergeService
{
    public function __construct(
        private LoggerInterface $log,
    ) {
    }

    public function mergeMovieMetadata(MediaMetadata $meta, array $tmdbData): void
    {
        if (!$tmdbData) {
            return;
        }

        // TMDB ID
        if (!empty($tmdbData['externalId'])) {
            $meta->setTmdbId((string) $tmdbData['externalId']);
        }

        // IMAGE (always use hydrated full URL)
        if (!empty($tmdbData['image'])) {
            $meta->setImage($tmdbData['image']);
        }

        // OVERVIEW
        if (!empty($tmdbData['overview'])) {
            $meta->setOverview($tmdbData['overview']);
        }

        // RUNTIME (TMDB authoritative)
        if (!empty($tmdbData['runtime']) && $tmdbData['runtime'] > 0) {
            $meta->setRuntime((int) $tmdbData['runtime']);
        }

        // GENRES
        if (!empty($tmdbData['genres'])) {
            $meta->setGenres($tmdbData['genres']);
        }

        // RELEASE DATE
        if (!$meta->getReleaseDate() && !empty($tmdbData['releaseDate'])) {
            try {
                $meta->setReleaseDate(new \DateTime($tmdbData['releaseDate']));
            } catch (\Throwable) {
                // ignore invalid TMDB dates
            }
        }

        $this->log->debug('meta.merge.movie.tmdb', [
            'tmdbId' => $meta->getTmdbId(),
        ]);
    }

    /**
     * Merge movie metadata from the multi-source discovery result.
     *
     * Strategy (roadmap Phase 3 / Phase 6): TMDB is the single authoritative
     * source for movies (identity, images, cast, IMDb rating). Missing fields
     * are filled from the discovered ShowFull, and the raw rating is preserved.
     *
     * @param array{tmdb:?ShowFull, best:string} $discovery
     */
    public function mergeMovieMetadataFromDiscovery(MediaMetadata $meta, array $discovery): void
    {
        $movie = $discovery['tmdb'] ?? null;
        if (!$movie) {
            return;
        }

        if ($movie->title) {
            $meta->setTitle($movie->title);
        }
        if ($movie->originalTitle) {
            $meta->setOriginalTitle($movie->originalTitle);
        }
        if ($movie->image) {
            $meta->setImage($movie->image);
        }
        if ($movie->overview) {
            $meta->setOverview($movie->overview);
        }
        if ($movie->genres) {
            $meta->setGenres($movie->genres);
        }
        if (!$meta->getRuntime() && $movie->runtime) {
            $meta->setRuntime($movie->runtime);
        }
        if (!$meta->getReleaseDate() && $movie->year) {
            try {
                $meta->setReleaseDate(new \DateTime((string) $movie->year.'-01-01'));
            } catch (\Throwable) {
            }
        }
        if ($movie->externalIds->tmdb) {
            $meta->setTmdbId($movie->externalIds->tmdb);
            $meta->setSource(Source::TMDB);
        }

        $this->log->debug('meta.merge.movie.discovery', [
            'tmdbId' => $meta->getTmdbId(),
            'best' => $discovery['best'] ?? 'unknown',
        ]);
    }

    public function mergeTvMetadata(MediaMetadata $meta, ?array $tmdbFull, ?array $mazeFull): void
    {
        // TMDB primary
        if ($tmdbFull && isset($tmdbFull['show'])) {
            $show = $tmdbFull['show'];

            $meta->setTmdbId((string) $show['id']);
            if (!empty($show['image'])) {
                $meta->setImage($show['image']);
            }
            $meta->setOverview($show['overview'] ?? $meta->getOverview());
            $meta->setRuntime($show['episode_run_time'][0] ?? $meta->getRuntime());
            $meta->setGenres($show['genres'] ?? $meta->getGenres());

            if (!$meta->getReleaseDate() && !empty($show['first_air_date'])) {
                $meta->setReleaseDate(new \DateTime($show['first_air_date']));
            }

            $this->log->debug('meta.merge.tv.tmdb', [
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

            $meta->setExternalId((string) $show['id']);

            $this->log->debug('meta.merge.tv.maze', [
                'mazeId' => $meta->getExternalId(),
            ]);
        }
    }

    /**
     * Merge TV metadata from the multi-source discovery result.
     *
     * Strategy (roadmap Phase 3):
     *  - TMDB wins for identity fields (ids, title, image, overview, genres).
     *  - TVDB wins for episode structure (external id when no TMDB).
     *  - TVMaze wins for status fields.
     *  - Missing fields are filled from the other available sources.
     *
     * @param array{tmdb:?ShowFull, tvdb:?ShowFull, tvmaze:?ShowFull, best:string} $discovery
     */
    public function mergeTvMetadataFromDiscovery(MediaMetadata $meta, array $discovery): void
    {
        $tmdb = $discovery['tmdb'] ?? null;
        $tvdb = $discovery['tvdb'] ?? null;
        $maze = $discovery['tvmaze'] ?? null;

        // ── Identity: TMDB first, then TVDB, then TVMaze ───────────────────
        $identity = $tmdb ?? $tvdb ?? $maze;
        if ($identity) {
            if ($identity->title) {
                $meta->setTitle($identity->title);
            }
            if ($identity->originalTitle) {
                $meta->setOriginalTitle($identity->originalTitle);
            }
            if ($identity->image) {
                $meta->setImage($identity->image);
            }
            if ($identity->overview) {
                $meta->setOverview($identity->overview);
            }
            if ($identity->genres) {
                $meta->setGenres($identity->genres);
            }
            if (!$meta->getRuntime() && $identity->runtime) {
                $meta->setRuntime($identity->runtime);
            }
            if (!$meta->getReleaseDate() && $identity->year) {
                try {
                    $meta->setReleaseDate(new \DateTime((string) $identity->year.'-01-01'));
                } catch (\Throwable) {
                }
            }
            if ($identity->country) {
                $meta->setCountry($identity->country);
            }

            if ($tmdb && $tmdb->externalIds->tmdb) {
                $meta->setTmdbId($tmdb->externalIds->tmdb);
                $meta->setSource(Source::TMDB);
            } elseif ($tvdb && $tvdb->externalIds->tvdb) {
                $meta->setExternalId($tvdb->externalIds->tvdb);
                $meta->setSource(Source::TVDB);
            } elseif ($maze && $maze->externalIds->tvmaze) {
                $meta->setExternalId($maze->externalIds->tvmaze);
                $meta->setSource(Source::TVMAZE);
            }

            $this->log->debug('meta.merge.tv.discovery', [
                'best' => $discovery['best'] ?? 'unknown',
                'tmdbId' => $meta->getTmdbId(),
                'externalId' => $meta->getExternalId(),
            ]);
        }

        // ── Status: TVMaze wins ────────────────────────────────────────────
        if ($maze && $maze->status) {
            $meta->setOverview($meta->getOverview() ?? null);
        }
    }

    public function mergeSeasonMetadata(MediaMetadata $meta, ?array $tmdbSeason, ?array $mazeSeason): void
    {
        if ($tmdbSeason) {
            $meta->setTmdbId((string) $tmdbSeason['id']);
            $meta->setOverview($tmdbSeason['overview'] ?? $meta->getOverview());
            if (!empty($tmdbSeason['image'])) {
                $meta->setImage($tmdbSeason['image']);
            }

            $this->log->debug('meta.merge.season.tmdb', [
                'tmdbId' => $meta->getTmdbId(),
            ]);
        }

        if ($mazeSeason) {
            if (!$meta->getImage()) {
                $meta->setImage($mazeSeason['image']['original'] ?? $mazeSeason['image']['medium'] ?? null);
            }

            if (!$meta->getOverview() && !empty($mazeSeason['summary'])) {
                $meta->setOverview(strip_tags($mazeSeason['summary']));
            }

            $meta->setExternalId((string) $mazeSeason['id']);

            $this->log->debug('meta.merge.season.maze', [
                'mazeId' => $meta->getExternalId(),
            ]);
        }
    }

    public function mergeEpisodeMetadata(MediaMetadata $meta, ?array $tmdbEpisode, ?array $mazeEpisode): void
    {
        if ($tmdbEpisode) {
            $meta->setTmdbId((string) $tmdbEpisode['id']);
            $meta->setOverview($tmdbEpisode['overview'] ?? $meta->getOverview());
            if (!empty($tmdbEpisode['image'])) {
                $meta->setImage($tmdbEpisode['image']);
            }

            if (!$meta->getScreenshot() && !empty($tmdbEpisode['screenshot'])) {
                $meta->setScreenshot($tmdbEpisode['screenshot']);
            }

            if (empty($meta->getCast()) && !empty($tmdbEpisode['cast'])) {
                $meta->setCast($tmdbEpisode['cast']);
            }

            if (!$meta->getTrailer() && !empty($tmdbEpisode['trailer'])) {
                $meta->setTrailer($tmdbEpisode['trailer']);
            }

            if (!$meta->getReleaseDate() && !empty($tmdbEpisode['air_date'])) {
                $meta->setReleaseDate(new \DateTime($tmdbEpisode['air_date']));
            }

            $this->log->debug('meta.merge.episode.tmdb', [
                'tmdbId' => $meta->getTmdbId(),
            ]);
        }

        if ($mazeEpisode) {
            if (!$meta->getImage()) {
                $meta->setImage($mazeEpisode['image']['original'] ?? $mazeEpisode['image']['medium'] ?? null);
            }

            if (!$meta->getScreenshot() && !empty($mazeEpisode['screenshot'])) {
                $meta->setScreenshot($mazeEpisode['screenshot']);
            }

            if (!$meta->getOverview() && !empty($mazeEpisode['summary'])) {
                $meta->setOverview(strip_tags($mazeEpisode['summary']));
            }

            if (!$meta->getReleaseDate() && !empty($mazeEpisode['airdate'])) {
                $meta->setReleaseDate(new \DateTime($mazeEpisode['airdate']));
            }

            $meta->setExternalId((string) $mazeEpisode['id']);

            $this->log->debug('meta.merge.episode.maze', [
                'mazeId' => $meta->getExternalId(),
            ]);
        }
    }
}
