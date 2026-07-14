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
use App\Service\Import\Metadata\MetadataHydrator;
use App\Service\Import\Metadata\MetadataLookupService;
use App\Service\Import\Metadata\MetadataMergeService;
use App\Service\TmdbService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class MovieImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TmdbService $tmdb,
        private MetadataLookupService $lookup,
        private MetadataMergeService $merge,
        private MetadataHydrator $hydrator,
        private LoggerInterface $log,
        private MovieMetadataImporter $metadataImporter,
        private MovieWatchHistoryImporter $watchHistoryImporter,
        private MovieDuplicateResolver $duplicateResolver,
    ) {
    }

    /**
     * Import a normalized movie record from ANY provider.
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

        // TMDB enrichment
        $tmdbData = $this->tmdb->findMovie($ids, $title, $record['year'] ?? null);

        if ($tmdbData) {
            $hydrated = $this->hydrator->hydrateTmdbMovie($tmdbData);
            $this->merge->mergeMovieMetadata($meta, $hydrated);

            $this->log->info('movie.tmdb.enriched', [
                'title' => $title,
                'tmdbId' => $hydrated['externalId'] ?? null,
            ]);
        }

        // Duplicate detection (cross-provider)
        $existing = $this->duplicateResolver->resolve($meta, $ids, $user);

        if ($existing) {
            $this->log->warning('movie.duplicate.merged', [
                'title' => $title,
                'meta' => $meta->getMediaId(),
            ]);

            // Merge watch history into existing movie
            $this->watchHistoryImporter->apply($existing, $record);

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

        // Ratings (Letterboxd, Simkl, etc.)
        $this->applyRatings($movie, $record);

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

    private function applyRatings(Movie $movie, array $record): void
    {
        if (!empty($record['rating'])) {
            $movie->setScore((float) $record['rating']);
        }
    }
}
