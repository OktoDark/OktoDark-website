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
use App\Enum\MediaType;
use App\Enum\Source;
use App\Service\Import\Metadata\Structure\ShowFull;
use App\Service\Import\Metadata\Structure\ShowFullFactory;
use App\Service\TmdbService;
use App\Service\TvdbService;
use App\Service\TvMazeService;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class MetadataLookupService
{
    public function __construct(
        private ManagerRegistry $registry,
        private LoggerInterface $log,
        private TmdbService $tmdb,
        private TvdbService $tvdb,
        private TvMazeService $maze,
    ) {
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->registry->getManager();
    }

    public function findOrCreateMetadata(
        MediaType $mediaType,
        array $ids,
        string $title,
        ?int $year,
        Source $source,
        ?int $seasonNumber = null,
        ?int $episodeNumber = null,
    ): MediaMetadata {
        $repo = $this->em()->getRepository(MediaMetadata::class);

        // TMDB
        if (!empty($ids['tmdb'])) {
            $meta = $repo->findOneBy([
                'tmdbId' => (string) $ids['tmdb'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // TVMaze
        if (!empty($ids['tvmaze'])) {
            $meta = $repo->findOneBy([
                'externalId' => (string) $ids['tvmaze'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // Alpha ID (used by movie providers)
        if (!empty($ids['alpha'])) {
            $meta = $repo->findOneBy([
                'mediaId' => (string) $ids['alpha'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // mediaId fallback (rare)
        if (!empty($ids['mediaId'])) {
            $meta = $repo->findOneBy([
                'mediaId' => (string) $ids['mediaId'],
                'mediaType' => $mediaType,
                'seasonNumber' => $seasonNumber,
                'episodeNumber' => $episodeNumber,
            ]);
            if ($meta) {
                return $meta;
            }
        }

        // Create new metadata
        $meta = new MediaMetadata();

        // Always assign a normalized mediaId
        $meta->setMediaId(
            mb_strtolower(
                mb_trim(
                    $ids['tmdb']
                    ?? $ids['alpha']
                    ?? $ids['slug']
                    ?? $ids['custom']
                    ?? bin2hex(random_bytes(16))
                )
            )
        );

        $meta->setMediaType($mediaType);
        $meta->setTitle($title);
        $meta->setSource($source);

        if ($year) {
            $meta->setYear($year);
        }

        if (null !== $seasonNumber) {
            $meta->setSeasonNumber($seasonNumber);
        }

        if (null !== $episodeNumber) {
            $meta->setEpisodeNumber($episodeNumber);
        }

        if (!empty($ids['tmdb'])) {
            $meta->setTmdbId((string) $ids['tmdb']);
        }

        if (!empty($ids['tvmaze'])) {
            $meta->setExternalId((string) $ids['tvmaze']);
        }

        $this->em()->persist($meta);

        return $meta;
    }

    /**
     * Multi-source discovery engine for TV shows.
     *
     * Fetches TMDB / TVDB / TVMaze when ids are known, discovers missing ids via
     * title+year search, scores every result and returns the normalized ShowFull
     * for each source plus the name of the best-scoring source.
     *
      * Source order (industry standard): TMDB → TVDB → TVMaze.
      *
      * @param array{tmdb?:string|int, tvdb?:string|int, tvmaze?:string|int, title?:string} $ids
      *
      * @return array{tmdb:?ShowFull, tvdb:?ShowFull, tvmaze:?ShowFull, best:string, scores:array<string,int>}
      */
    public function discoverTvSources(string $title, ?int $year, array $ids): array
    {
        $cleanIds = [
            'tmdb' => !empty($ids['tmdb']) ? (string) $ids['tmdb'] : null,
            'tvdb' => !empty($ids['tvdb']) ? (string) $ids['tvdb'] : null,
            'tvmaze' => !empty($ids['tvmaze']) ? (string) $ids['tvmaze'] : null,
        ];

        $tmdbFull = null;
        $tvdbFull = null;
        $mazeFull = null;

        // ── Fetch by known id ──────────────────────────────────────────────
        if ($cleanIds['tmdb']) {
            $tmdbFull = ShowFullFactory::fromRaw($this->tmdb->fetchFullShow((int) $cleanIds['tmdb']), 'tmdb');
        }

        if ($cleanIds['tvdb'] && $this->tvdb->isConfigured()) {
            $tvdbFull = ShowFullFactory::fromRaw($this->tvdb->fetchFullShow((int) $cleanIds['tvdb']), 'tvdb');
        }

        if ($cleanIds['tvmaze']) {
            $mazeFull = ShowFullFactory::fromRaw($this->maze->fetchFullShow((int) $cleanIds['tvmaze']), 'tvmaze');
        }

        // ── Discover missing ids via title search ─────────────────────────
        if (!$tmdbFull && $title) {
            $tmdbFull = $this->discoverBySearch('tmdb', $title, $year);
        }
        if (!$tvdbFull && $this->tvdb->isConfigured() && $title) {
            $tvdbFull = $this->discoverBySearch('tvdb', $title, $year, $cleanIds);
        }
        if (!$mazeFull && $title) {
            $mazeFull = $this->discoverBySearch('tvmaze', $title, $year);
        }

        // ── Score every resolved source ───────────────────────────────────
        $scores = [
            'tmdb' => $tmdbFull ? $this->score($tmdbFull, $title, $year, $cleanIds) : 0,
            'tvdb' => $tvdbFull ? $this->score($tvdbFull, $title, $year, $cleanIds) : 0,
            'tvmaze' => $mazeFull ? $this->score($mazeFull, $title, $year, $cleanIds) : 0,
        ];

        $best = 'tmdb';
        $bestScore = -1;
        foreach ($scores as $source => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $source;
            }
        }
        // If nothing scored, still prefer TMDB when present (identity source).
        if ($bestScore <= 0 && $tmdbFull) {
            $best = 'tmdb';
        }

        $this->log->debug('tv.discovery.scores', [
            'title' => $title,
            'scores' => $scores,
            'best' => $best,
        ]);

        return [
            'tmdb' => $tmdbFull,
            'tvdb' => $tvdbFull,
            'tvmaze' => $mazeFull,
            'best' => $best,
            'scores' => $scores,
        ];
    }

    /**
     * Multi-source discovery engine for MOVIES.
     *
     * Mirrors {@see discoverTvSources()} but only TMDB participates (the roadmap
     * defines TVDB/TVMaze as N/A for movies). The method still resolves a TMDB
     * movie by known id, falls back to a title+year search, and returns the
     * normalized ShowFull plus a best-source marker so the movie importer shares
     * the exact same merge/hierarchy contract as the TV importer.
     *
      * @param array{tmdb?:string|int, title?:string} $ids
      *
      * @return array{tmdb:?ShowFull, best:string, scores:array<string,int>}
      */
    public function discoverMovieSources(string $title, ?int $year, array $ids): array
    {
        $tmdbId = !empty($ids['tmdb']) ? (int) $ids['tmdb'] : null;

        $tmdbFull = null;
        if ($tmdbId) {
            $raw = $this->tmdb->findMovie(['tmdb' => $tmdbId], $title, $year);
            if ($raw) {
                $tmdbFull = ShowFullFactory::fromMovieRaw($raw);
            }
        }

        if (!$tmdbFull && $title) {
            $raw = $this->tmdb->findMovie([], $title, $year);
            if ($raw) {
                $tmdbFull = ShowFullFactory::fromMovieRaw($raw);
            }
        }

        $scores = [
            'tmdb' => $tmdbFull ? $this->scoreMovie($tmdbFull, $title, $year, $ids) : 0,
        ];

        $best = $tmdbFull ? 'tmdb' : '';

        $this->log->debug('movie.discovery.scores', [
            'title' => $title,
            'scores' => $scores,
            'best' => $best,
        ]);

        return [
            'tmdb' => $tmdbFull,
            'best' => $best,
            'scores' => $scores,
        ];
    }

    /**
     * @param array{tmdb?:string|null, tvdb?:string|null, tvmaze?:string|null} $ids
     */
    private function discoverBySearch(string $source, string $title, ?int $year, array $ids = []): ?ShowFull
    {
        if ('tmdb' === $source) {
            foreach ($this->tmdb->searchShowList($title, $year) as $hit) {
                $full = ShowFullFactory::fromRaw($this->tmdb->fetchFullShow((int) ($hit['id'] ?? 0)), 'tmdb');
                if ($full) {
                    return $full;
                }
            }
        } elseif ('tvdb' === $source) {
            $searchIds = ['tvdb' => $ids['tvdb'] ?? null];
            $tvdbId = $this->tvdb->findShowId($searchIds, $title, $year);
            if ($tvdbId) {
                return ShowFullFactory::fromRaw($this->tvdb->fetchFullShow($tvdbId), 'tvdb');
            }
        } elseif ('tvmaze' === $source) {
            foreach ($this->maze->searchShowList($title, $year) as $hit) {
                $full = ShowFullFactory::fromRaw($this->maze->fetchFullShow((int) ($hit['id'] ?? 0)), 'tvmaze');
                if ($full) {
                    return $full;
                }
            }
        }

        return null;
    }

    /**
     * Score a resolved source against the requested title/year/ids.
     *
     * Weights (higher = more confident):
     *  - External id consistency ...... +40 (per matching id)
     *  - Title match ................... +30
     *  - Year match .................... +15
     *  - Season count .................. +1 per season (cap 10)
     *  - Episode count ................. +1 per 10 episodes (cap 20)
     *  - Country match ................. +5
     *  - Network match ................. +5
     */
    private function score(ShowFull $show, string $title, ?int $year, array $ids): int
    {
        $score = 0;

        // External id consistency
        $ext = $show->externalIds;
        if ($ext->tmdb && ($ids['tmdb'] ?? null) && (string) $ext->tmdb === (string) $ids['tmdb']) {
            $score += 40;
        }
        if ($ext->tvdb && ($ids['tvdb'] ?? null) && (string) $ext->tvdb === (string) $ids['tvdb']) {
            $score += 40;
        }
        if ($ext->tvmaze && ($ids['tvmaze'] ?? null) && (string) $ext->tvmaze === (string) $ids['tvmaze']) {
            $score += 40;
        }

        // Title match (loose, case/space insensitive)
        if ($show->title) {
            $a = mb_strtolower(preg_replace('/\s+/', '', $show->title));
            $b = mb_strtolower(preg_replace('/\s+/', '', $title));
            if ($a === $b) {
                $score += 30;
            } elseif (str_contains($a, $b) || str_contains($b, $a)) {
                $score += 15;
            }
        }

        // Year match
        if ($year && $show->year && abs($show->year - $year) <= 1) {
            $score += 15;
        }

        // Season / episode richness
        $score += min(10, $show->seasonCount());
        $score += min(20, (int) ($show->episodeCount() / 10));

        // Country match
        if ($show->country && !empty($ids['country']) && false !== mb_stripos((string) $show->country, (string) $ids['country'])) {
            $score += 5;
        }

        // Country match
        if ($show->country && !empty($ids['country']) && false !== mb_stripos((string) $show->country, (string) $ids['country'])) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Score a resolved movie source against the requested title/year/ids.
     *
     * Lighter than the TV score (movies have no seasons/episodes): external id
     * consistency + title match + year match.
     */
    private function scoreMovie(ShowFull $show, string $title, ?int $year, array $ids): int
    {
        $score = 0;

        if ($show->externalIds->tmdb && ($ids['tmdb'] ?? null) && (string) $show->externalIds->tmdb === (string) $ids['tmdb']) {
            $score += 40;
        }

        if ($show->title) {
            $a = mb_strtolower(preg_replace('/\s+/', '', $show->title));
            $b = mb_strtolower(preg_replace('/\s+/', '', $title));
            if ($a === $b) {
                $score += 30;
            } elseif (str_contains($a, $b) || str_contains($b, $a)) {
                $score += 15;
            }
        }

        if ($year && $show->year && abs($show->year - $year) <= 1) {
            $score += 15;
        }

        return $score;
    }
}
