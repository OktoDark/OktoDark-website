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

/**
 * Flattens the nested TVTime series JSON export (series -> seasons -> episodes)
 * into the flat TVTime v2 CSV schema consumed by TvTimeCsvImporter.
 *
 * The generator is strictly read-only: it writes a CSV file and never touches
 * the database. Import is delegated to the existing, tested importer so the
 * OktoDark entity model (MediaMetadata / TV / Season / Episode) and the TVMaze
 * enrichment pipeline stay identical to every other TVTime import.
 */
class JsonToCsvConverter
{
    /**
     * Canonical TVTime v2 CSV header expected by TvTimeCsvImporter::normalizeRow.
     */
    private const HEADER = [
        'key', 'ep_id', 'created_at', 'gsi', 'runtime', 'episode_id', 'series_name',
        'episode_number', 'user_id', 's_no', 'season_number', 'ep_no', 's_id',
        'total_series_runtime', 'ep_watch_count', 'total_movies_runtime', 'updated_at',
        'series_follow_count', 'movie_watch_count', 'is_followed', 'is_for_later', 'uuid',
        'most_recent_ep_watched', 'is_archived', 'followed_at', 'rewatch_count',
        'is_unitary', 'is_special', 'bulk_type',
    ];

    /**
     * @param int|null $userId optional owner user id stamped into every row's user_id column
     *
     * @return array{series: int, watched_rows: int, series_rows: int, skipped: int, errors: int}
     */
    public function convert(string $inputFile, string $outputFile, ?int $userId = null): array
    {
        $stats = ['series' => 0, 'watched_rows' => 0, 'series_rows' => 0, 'skipped' => 0, 'errors' => 0];

        $userIdStr = null === $userId ? '' : (string) $userId;

        $raw = file_get_contents($inputFile);
        $data = json_decode($raw, true);

        if (!\is_array($data)) {
            throw new \RuntimeException("Invalid JSON in {$inputFile}");
        }

        $handle = fopen($outputFile, 'w');
        if (false === $handle) {
            throw new \RuntimeException("Cannot write to {$outputFile}");
        }

        fputcsv($handle, self::HEADER, ',', '"', '\\');

        foreach ($data as $series) {
            if (!$this->isSeries($series)) {
                ++$stats['skipped'];

                continue;
            }

            ++$stats['series'];

            $title = (string) ($series['title'] ?? '');
            $uuid = (string) ($series['uuid'] ?? '');
            $status = (string) ($series['status'] ?? '');

            $resume = null;
            $maxWatched = 0;
            $hasAnyWatch = false;

            foreach ($series['seasons'] ?? [] as $season) {
                $seasonNumber = (int) ($season['number'] ?? 0);

                foreach ($season['episodes'] ?? [] as $episode) {
                    if (empty($episode['is_watched'])) {
                        continue;
                    }

                    $watchedAt = $episode['watched_at'] ?? null;
                    if (null === $watchedAt || '' === $watchedAt) {
                        continue;
                    }

                    $hasAnyWatch = true;
                    $episodeNumber = (int) ($episode['number'] ?? 0);
                    $maxWatched = max($maxWatched, $seasonNumber * 10000 + $episodeNumber);

                    $this->writeWatchRow($handle, $series, $seasonNumber, $episode, $watchedAt, $userIdStr);
                    ++$stats['watched_rows'];
                }
            }

            if ($maxWatched > 0) {
                $resume = [
                    'season' => (int) ($maxWatched / 10000),
                    'episode' => $maxWatched % 10000,
                ];
            }

            $this->writeSeriesRow($handle, $title, $uuid, $status, $resume, $hasAnyWatch, $userIdStr);
            ++$stats['series_rows'];
        }

        fclose($handle);

        return $stats;
    }

    private function isSeries(mixed $series): bool
    {
        return \is_array($series)
            && isset($series['title'])
            && isset($series['seasons']);
    }

    /**
     * Emit one watched-episode row in the TVTime v2 CSV schema.
     */
    private function writeWatchRow($handle, array $series, int $seasonNumber, array $episode, string $watchedAt, string $userId): void
    {
        $episodeId = (int) ($episode['id']['tvdb'] ?? 0);
        $episodeNumber = (int) ($episode['number'] ?? 0);
        $uuid = (string) ($series['uuid'] ?? '');
        $suffix = bin2hex(random_bytes(4));

        $row = [
            'key' => "watch-episode-{$uuid}-{$suffix}",
            'ep_id' => $episodeId,
            'created_at' => $watchedAt,
            'gsi' => 'watch-episode-'.time(),
            'runtime' => '',
            'episode_id' => $episodeId,
            'series_name' => $series['title'],
            'episode_number' => $episodeNumber,
            'user_id' => $userId,
            's_no' => $seasonNumber,
            'season_number' => $seasonNumber,
            'ep_no' => $episodeNumber,
            's_id' => '',
            'total_series_runtime' => '',
            'ep_watch_count' => (int) ($episode['watched_count'] ?? 1),
            'total_movies_runtime' => '',
            'updated_at' => '',
            'series_follow_count' => '',
            'movie_watch_count' => '',
            'is_followed' => '',
            'is_for_later' => '',
            'uuid' => $uuid,
            'most_recent_ep_watched' => '',
            'is_archived' => '',
            'followed_at' => '',
            'rewatch_count' => (int) ($episode['rewatch_count'] ?? 0),
            'is_unitary' => '',
            'is_special' => !empty($episode['special']) ? 'true' : 'false',
            'bulk_type' => '',
        ];

        fputcsv($handle, $row, ',', '"', '\\');
    }

    /**
     * Emit one followed-series row (user-series-* key) so the importer also
     * registers the show and can apply a resume point.
     */
    private function writeSeriesRow($handle, string $title, string $uuid, string $status, ?array $resume, bool $hasAnyWatch, string $userId): void
    {
        $mru = '';
        if ($resume) {
            $mru = "s_no:{$resume['season']},ep_no:{$resume['episode']}";
        }

        $row = [
            'key' => "user-series-{$uuid}",
            'ep_id' => '',
            'created_at' => '',
            'gsi' => '',
            'runtime' => '',
            'episode_id' => '',
            'series_name' => $title,
            'episode_number' => '',
            'user_id' => $userId,
            's_no' => '',
            'season_number' => '',
            'ep_no' => '',
            's_id' => '',
            'total_series_runtime' => '',
            'ep_watch_count' => '',
            'total_movies_runtime' => '',
            'updated_at' => '',
            'series_follow_count' => '',
            'movie_watch_count' => '',
            'is_followed' => $hasAnyWatch ? 'true' : 'false',
            'is_for_later' => '',
            'uuid' => $uuid,
            'most_recent_ep_watched' => $mru,
            'is_archived' => '',
            'followed_at' => '',
            'rewatch_count' => '',
            'is_unitary' => '',
            'is_special' => '',
            'bulk_type' => '',
        ];

        fputcsv($handle, $row, ',', '"', '\\');
    }
}
