<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie;

use App\Entity\Movie;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MovieImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private LoggerInterface $log,
        private MovieMetadataImporter $metadataImporter,
        private MovieWatchHistoryImporter $watchHistoryImporter,
        private MovieDuplicateResolver $duplicateResolver,
    ) {
    }

    /**
     * Import a normalized movie record from ANY provider.
     *
     * Provider-agnostic (TVTime, Trakt, Letterboxd, Simkl, Custom): only the
     * parsed title / year / ids matter. Uses the same multi-source discovery +
     * merge engine as the TV importer for metadata parity.
     */
    public function import(array $record): void
    {
        $title = $record['title'] ?? 'Unknown';
        $ids = $record['ids'] ?? [];
        $user = $record['user'];

        $this->log->info('movie.import.start', [
            'title' => $title,
            'source' => $record['source'] ?? 'unknown',
            'ids' => $ids,
        ]);

        // Detect or create metadata
        $meta = $this->lookup->findOrCreateMetadata(
            mediaType: MediaType::MOVIE,
            ids: $ids,
            title: $title,
            year: $record['year'] ?? null,
            source: $this->detectSource($record),
        );

        // Provider-specific metadata (runtime, release_date, etc.)
        $this->metadataImporter->apply($meta, $record);

        /*
         * ⭐ Multi-source discovery (TMDB → identity + images + cast)
         * Resolves the movie by known id, discovers via title+year search when
         * missing, and merges the result using the same strategy as the TV flow.
         */
        $discovery = $this->lookup->discoverMovieSources($title, $record['year'] ?? null, $ids);
        $this->merge->mergeMovieMetadataFromDiscovery($meta, $discovery);

        if ($discovery['tmdb'] ?? null) {
            $this->log->info('movie.tmdb.enriched', [
                'title' => $title,
                'tmdbId' => $discovery['tmdb']->externalIds->tmdb,
            ]);
        }

        // Duplicate detection (cross-provider)
        $existing = $this->duplicateResolver->resolve($meta, $ids, $user);

        if ($existing) {
            $this->log->warning('movie.duplicate.merged', [
                'title' => $title,
                'meta' => $meta->getMediaId(),
            ]);

            // Merge metadata + watch history into existing movie
            $this->metadataImporter->apply($existing, $record);
            $this->watchHistoryImporter->apply($existing, $record);
            $this->applyRatings($existing, $record, $discovery['tmdb'] ?? null);

            $this->em->flush();

            return;
        }

        // Create new movie entity
        $movie = new Movie();
        $movie->setMediaMetadata($meta);
        $movie->setUser($user);

        $this->em->persist($movie);

        // Watch history (status, progress, dates)
        $this->watchHistoryImporter->apply($movie, $record);

        // Ratings (provider score, else TMDB vote average)
        $this->applyRatings($movie, $record, $discovery['tmdb'] ?? null);

        $this->em->flush();

        $this->log->info('movie.import.success', [
            'title' => $title,
            'meta' => $meta->getMediaId(),
        ]);
    }

    private function detectSource(array $record): Source
    {
        return match ($record['source'] ?? '') {
            'tvtime' => Source::TVTIME,
            'trakt' => Source::TRAKT,
            'letterboxd' => Source::LETTERBOXD,
            'simkl' => Source::SIMKL,
            'custom' => Source::CUSTOM,
            default => Source::UNKNOWN,
        };
    }

    private function applyRatings(Movie $movie, array $record, ?\App\Service\Import\Metadata\Structure\ShowFull $tmdb = null): void
    {
        if (!empty($record['rating'])) {
            $movie->setScore((float) $record['rating']);

            return;
        }

        // Fall back to the TMDB user score (vote_average) when the provider
        // did not supply one — parity with the TV importer ratings flow.
        if (null !== $tmdb?->rating) {
            $movie->setScore($tmdb->rating);
        }
    }
}
