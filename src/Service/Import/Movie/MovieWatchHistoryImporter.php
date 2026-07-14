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
use App\Enum\WatchStatus;

class MovieWatchHistoryImporter
{
    public function apply(Movie $movie, array $record): void
    {
        // WATCH DATE → start_date / end_date
        if (!empty($record['watched_at'])) {
            try {
                $date = new \DateTime($record['watched_at']);
                $movie->setStartDate($date);
                $movie->setEndDate($date);
            } catch (\Throwable) {
                // ignore invalid dates
            }
        }

        // STATUS (convert string → enum)
        $movie->setStatus($this->mapStatus($record));

        // PROGRESS
        if (isset($record['progress'])) {
            $movie->setProgress((int) $record['progress']);
        } else {
            $movie->setProgress(!empty($record['watched_at']) ? 100 : 0);
        }

        // PROGRESSED_AT
        if (!empty($record['updated_at'])) {
            try {
                $movie->setProgressedAt(new \DateTime($record['updated_at']));
            } catch (\Throwable) {
            }
        }

        // NOTES (rewatches, etc.)
        $notes = [];

        if (!empty($record['watch_count'])) {
            $notes[] = "Watched {$record['watch_count']} times";
        }

        if (!empty($record['rewatch_count'])) {
            $notes[] = "Rewatched {$record['rewatch_count']} times";
        }

        if ($notes) {
            $movie->setNotes(implode('; ', $notes));
        }
    }

    /**
     * Convert provider status → WatchStatus enum.
     */
    private function mapStatus(array $record): WatchStatus
    {
        // Provider status string
        $status = mb_strtolower($record['status'] ?? '');

        return match ($status) {
            'completed', 'complete', 'finished', 'done' => WatchStatus::COMPLETED,
            'in_progress', 'watching', 'progress', 'current' => WatchStatus::IN_PROGRESS,
            'paused' => WatchStatus::PAUSED,
            'dropped', 'abandoned' => WatchStatus::DROPPED,
            'planning', 'planned', 'new', 'queued' => WatchStatus::PLANNING,

            // Fallback: if watched_at exists → completed
            default => !empty($record['watched_at'])
                ? WatchStatus::COMPLETED
                : WatchStatus::PLANNING,
        };
    }
}
