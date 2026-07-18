<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;
use App\Security\Attribute\Permission;
use App\Service\TmdbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/import')]
class ImportDashboardController extends AbstractController
{
    /**
     * Initialize the controller with the TMDB metadata service.
     */
    public function __construct(
        private TmdbService $tmdb,
    ) {
    }

    /**
     * Render the import dashboard showing session-stored operation logs.
     */
    #[Route('/', name: 'admin_import_dashboard', methods: ['GET'])]
    #[Permission('admin.import.dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $logs = $session->get('admin_import_logs', []);

        return $this->render('@theme/admin/import/dashboard.html.twig', [
            'logs' => $logs,
            'projectDir' => $this->getParameter('kernel.project_dir'),
        ]);
    }

    /**
     * Remove movie-typed season and TV tracking rows to clean up stale data.
     */
    #[Route('/cleanup', name: 'admin_import_cleanup', methods: ['POST'])]
    #[Permission('admin.import.cleanup')]
    public function cleanup(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $logs = [];
        $conn = $em->getConnection();

        $conn->executeStatement("
            DELETE s
            FROM tracking_season s
            JOIN tracking_item ti ON ti.id = s.media_metadata_id
            WHERE ti.media_type = 'movie'
        ");

        $conn->executeStatement("
            DELETE tv
            FROM tracking_tv tv
            JOIN tracking_item ti ON ti.id = tv.media_metadata_id
            WHERE ti.media_type = 'movie'
        ");

        $logs[] = 'Cleanup completed.';
        $session->set('admin_import_logs', $logs);

        return $this->json(['status' => 'cleanup_done', 'logs' => $logs]);
    }

    /**
     * Delete duplicate tracking rows across metadata, movie, TV, season and episode tables.
     */
    #[Route('/remove-duplicates', name: 'admin_import_remove_duplicates', methods: ['POST', 'GET'])]
    #[Permission('admin.import.remove_duplicates')]
    public function removeDuplicates(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $conn = $em->getConnection();
        $logs = [];

        // Remove duplicate MediaMetadata (tracking_item)
        $conn->executeStatement('
        DELETE ti FROM tracking_item ti
        JOIN (
            SELECT MIN(id) AS keep_id, media_id, source, media_type, season_number, episode_number
            FROM tracking_item
            GROUP BY media_id, source, media_type, season_number, episode_number
            HAVING COUNT(*) > 1
        ) d ON ti.media_id = d.media_id
           AND ti.source = d.source
           AND ti.media_type = d.media_type
           AND ti.season_number <=> d.season_number
           AND ti.episode_number <=> d.episode_number
        WHERE ti.id <> d.keep_id
    ');
        $logs[] = 'Removed duplicate MediaMetadata entries.';

        // Remove duplicate Movies
        $conn->executeStatement('
        DELETE tm FROM tracking_movie tm
        JOIN (
            SELECT MIN(id) AS keep_id, media_metadata_id, user_id
            FROM tracking_movie
            GROUP BY media_metadata_id, user_id
            HAVING COUNT(*) > 1
        ) d ON tm.media_metadata_id = d.media_metadata_id
           AND tm.user_id = d.user_id
        WHERE tm.id <> d.keep_id
    ');
        $logs[] = 'Removed duplicate Movies.';

        // Remove duplicate TV entries
        $conn->executeStatement('
        DELETE tv FROM tracking_tv tv
        JOIN (
            SELECT MIN(id) AS keep_id, media_metadata_id, user_id
            FROM tracking_tv
            GROUP BY media_metadata_id, user_id
            HAVING COUNT(*) > 1
        ) d ON tv.media_metadata_id = d.media_metadata_id
           AND tv.user_id = d.user_id
        WHERE tv.id <> d.keep_id
    ');
        $logs[] = 'Removed duplicate TV entries.';

        // Remove duplicate Seasons
        $conn->executeStatement('
        DELETE ts FROM tracking_season ts
        JOIN (
            SELECT MIN(id) AS keep_id, related_tv_id, media_metadata_id
            FROM tracking_season
            GROUP BY related_tv_id, media_metadata_id
            HAVING COUNT(*) > 1
        ) d ON ts.related_tv_id = d.related_tv_id
           AND ts.media_metadata_id = d.media_metadata_id
        WHERE ts.id <> d.keep_id
    ');
        $logs[] = 'Removed duplicate Seasons.';

        // Remove duplicate Episodes
        $conn->executeStatement('
        DELETE te FROM tracking_episode te
        JOIN (
            SELECT MIN(id) AS keep_id, media_metadata_id, related_season_id
            FROM tracking_episode
            GROUP BY media_metadata_id, related_season_id
            HAVING COUNT(*) > 1
        ) d ON te.media_metadata_id = d.media_metadata_id
           AND te.related_season_id = d.related_season_id
        WHERE te.id <> d.keep_id
    ');
        $logs[] = 'Removed duplicate Episodes.';

        $session->set('admin_import_logs', $logs);

        return $this->json(['status' => 'duplicates_removed', 'logs' => $logs]);
    }

    /**
     * Re-fetch and re-hydrate TMDB metadata for every MediaMetadata entity.
     */
    #[Route('/rebuild-metadata', name: 'admin_import_rebuild_metadata', methods: ['POST'])]
    #[Permission('admin.import.rebuild_metadata')]
    public function rebuildMetadata(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $repo = $em->getRepository(MediaMetadata::class);
        $items = $repo->findAll();
        $logs = [];
        $skipped = 0;

        foreach ($items as $meta) {
            $tmdbId = $meta->getExternalId();
            $source = $meta->getSource();

            if (MediaType::MOVIE === $meta->getMediaType()) {
                if (empty($tmdbId) || Source::TMDB !== $source) {
                    ++$skipped;
                    continue;
                }

                $tmdbData = $this->tmdb->findMovie(['tmdb' => $tmdbId]);

                if (!$tmdbData) {
                    $logs[] = "Skipped (not found): {$meta->getTitle()}";
                    ++$skipped;
                    continue;
                }

                $hydrated = $this->tmdb->hydrateMetadata($tmdbData);

                $meta->setTitle($hydrated['title']);
                $meta->setImage($hydrated['image']);
                $meta->setOverview($hydrated['overview']);
                $meta->setGenres($hydrated['genres']);
                $meta->setRuntime($hydrated['runtime']);
                $meta->setExternalId((string) $hydrated['externalId']);
                $meta->setCountry($hydrated['country']);

                if (!empty($hydrated['releaseDate'])) {
                    $meta->setReleaseDate(new \DateTime($hydrated['releaseDate']));
                    $meta->setYear((int) mb_substr($hydrated['releaseDate'], 0, 4));
                }

                $logs[] = "Rebuilt movie: {$meta->getTitle()}";
            } elseif (MediaType::TV === $meta->getMediaType()) {
                if (empty($tmdbId) || Source::TMDB !== $source) {
                    ++$skipped;
                    continue;
                }

                $tmdbData = $this->tmdb->findShow(['tmdb' => $tmdbId]);

                if (!$tmdbData) {
                    $logs[] = "Skipped (not found): {$meta->getTitle()}";
                    ++$skipped;
                    continue;
                }

                $hydrated = $this->tmdb->hydrateMetadata($tmdbData);

                $meta->setTitle($hydrated['title']);
                $meta->setImage($hydrated['image']);
                $meta->setOverview($hydrated['overview']);
                $meta->setGenres($hydrated['genres']);
                $meta->setRuntime($hydrated['runtime']);
                $meta->setExternalId((string) $hydrated['externalId']);
                $meta->setCountry($hydrated['country']);

                if (!empty($hydrated['releaseDate'])) {
                    $meta->setReleaseDate(new \DateTime($hydrated['releaseDate']));
                    $meta->setYear((int) mb_substr($hydrated['releaseDate'], 0, 4));
                }

                $logs[] = "Rebuilt TV: {$meta->getTitle()}";
            }
        }

        $logs[] = "Skipped {$skipped} items without TMDB ID.";
        $session->set('admin_import_logs', $logs);
        $em->flush();

        return $this->json(['status' => 'metadata_rebuilt', 'logs' => $logs]);
    }

    /**
     * Launch the tracker:backfill-metadata console command in the background.
     *
     * The command can take a long time for tens of thousands of rows, so it is
     * spawned as a detached process and its output streamed to a log file that
     * the matching status endpoint polls. The detached child PID is recorded in
     * a pidfile so a long run can be interrupted via the Stop endpoint. Refuses
     * to start while a previous run is still in progress.
     */
    #[Route('/backfill-metadata', name: 'admin_import_backfill_metadata', methods: ['POST'])]
    #[Permission('admin.import.backfill_metadata')]
    public function backfillMetadata(Request $request): JsonResponse
    {
        [$logPath, $pidPath, $launcherPath] = $this->backfillPaths();

        if ($this->backfillIsRunning($pidPath)) {
            return new JsonResponse([
                'status' => 'already_running',
                'log' => is_file($logPath) ? (string) @file_get_contents($logPath) : '',
            ]);
        }

        $type = (string) $request->request->get('type', '');
        $source = (string) $request->request->get('source', '');
        $limit = $request->request->getInt('limit', 0);
        $batch = $request->request->getInt('batch', 200);
        $delay = $request->request->getInt('delay', 100);
        $apply = (bool) $request->request->get('apply', false);

        $args = ['tracker:backfill-metadata'];
        if ('' !== $type) {
            $args[] = '--type='.escapeshellarg($type);
        }
        if ('' !== $source) {
            $args[] = '--source='.escapeshellarg($source);
        }
        if ($limit > 0) {
            $args[] = '--limit='.$limit;
        }
        if ($batch > 0) {
            $args[] = '--batch='.$batch;
        }
        if ($delay >= 0) {
            $args[] = '--delay-ms='.$delay;
        }
        if ($apply) {
            $args[] = '--apply';
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $console = $projectDir.'/bin/console';

        $phpCmd = escapeshellarg(\PHP_BINARY).' '.escapeshellarg($console).' '.implode(' ', array_map('escapeshellarg', $args));
        $launcherCmd = $phpCmd.' >> '.escapeshellarg($logPath).' 2>&1';

        $this->writeLauncher($launcherPath, $launcherCmd);
        @file_put_contents($logPath, '');
        @unlink($pidPath);

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $launcherArg = '"'.str_replace('"', '', $launcherPath).'"';
            $pidArg = '"'.str_replace('"', '', $pidPath).'"';
            $command = 'powershell -NoProfile -Command "$p = Start-Process -FilePath \'cmd.exe\' '
                .'-ArgumentList \'/C\', '.$launcherArg.' -NoNewWindow -PassThru; '
                .'$p.Id | Out-File -FilePath '.$pidArg.' -Encoding ascii"';
        } else {
            $command = 'nohup /bin/sh '.escapeshellarg($launcherPath).' >/dev/null 2>&1 & echo $! > '.escapeshellarg($pidPath);
        }

        $handle = @popen($command, 'r');
        if (false === $handle) {
            return new JsonResponse(['status' => 'error', 'message' => 'Unable to start backfill process.'], 500);
        }
        pclose($handle);

        return new JsonResponse(['status' => 'started', 'log' => '']);
    }

    /**
     * Stop a running backfill by killing the detached process recorded in the
     * pidfile. Trailing child processes (the PHP worker) are terminated too.
     */
    #[Route('/backfill-metadata/stop', name: 'admin_import_backfill_metadata_stop', methods: ['POST'])]
    #[Permission('admin.import.backfill_metadata')]
    public function backfillMetadataStop(): JsonResponse
    {
        [$logPath, $pidPath] = $this->backfillPaths();

        $wasRunning = $this->backfillIsRunning($pidPath);
        $this->backfillKill($pidPath, $logPath);

        return new JsonResponse([
            'status' => $wasRunning ? 'stopped' : 'not_running',
            'log' => is_file($logPath) ? (string) @file_get_contents($logPath) : '',
        ]);
    }

    /**
     * Stream the running backfill command's log file to the dashboard.
     */
    #[Route('/backfill-metadata/status', name: 'admin_import_backfill_metadata_status', methods: ['GET'])]
    #[Permission('admin.import.backfill_metadata')]
    public function backfillMetadataStatus(): JsonResponse
    {
        [$logPath, $pidPath] = $this->backfillPaths();

        if (!is_file($logPath)) {
            return new JsonResponse(['running' => false, 'log' => '']);
        }

        $log = (string) @file_get_contents($logPath);
        $running = $this->backfillIsRunning($pidPath) && !str_contains($log, 'Done.');

        return new JsonResponse(['running' => $running, 'log' => $log]);
    }

    /**
     * Resolve the on-disk paths used by the detached backfill (log, pidfile, launcher).
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function backfillPaths(): array
    {
        $logDir = $this->getParameter('kernel.project_dir').'/var/log';

        return [
            $logDir.'/backfill_metadata.log',
            $logDir.'/backfill_metadata.pid',
            $logDir.'/backfill_metadata.launcher'.('\\' === \DIRECTORY_SEPARATOR ? '.cmd' : '.sh'),
        ];
    }

    /**
     * Whether a backfill process is currently alive (pidfile present + live PID).
     */
    private function backfillIsRunning(string $pidPath): bool
    {
        if (!is_file($pidPath)) {
            return false;
        }

        $pid = (int) @file_get_contents($pidPath);

        return $pid > 0 && $this->pidAlive($pid);
    }

    /**
     * Probe a process for liveness in a cross-platform way (kill -0 / Get-Process).
     */
    private function pidAlive(int $pid): bool
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $out = @shell_exec('powershell -NoProfile -Command "Get-Process -Id '.$pid.' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id"');

            return null !== $out && '' !== mb_trim((string) $out);
        }

        $code = @shell_exec('kill -0 '.$pid.' 2>/dev/null; echo $?');

        return '0' === mb_trim((string) $code);
    }

    /**
     * Terminate the detached backfill (and its child tree) and mark the log as stopped.
     */
    private function backfillKill(string $pidPath, string $logPath): void
    {
        if (is_file($pidPath)) {
            $pid = (int) @file_get_contents($pidPath);

            if ($pid > 0) {
                if ('\\' === \DIRECTORY_SEPARATOR) {
                    @shell_exec('taskkill /PID '.$pid.' /T /F');
                } else {
                    @shell_exec('kill -TERM '.$pid.' 2>/dev/null');
                }
            }

            @unlink($pidPath);
        }

        $existing = is_file($logPath) ? (string) @file_get_contents($logPath) : '';
        @file_put_contents($logPath, mb_rtrim($existing)."\n=== Stopped by user ===\nDone. (stopped)\n");
    }

    /**
     * Write a small launcher script that runs the backfill command with output
     * redirected to the log file. Using a script avoids shell-quoting issues
     * with the long argument list across platforms.
     */
    private function writeLauncher(string $launcherPath, string $command): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            @file_put_contents($launcherPath, "@echo off\r\n".$command."\r\n");
        } else {
            @file_put_contents($launcherPath, "#!/bin/sh\n".'exec '.$command."\n");
        }
    }

    /**
     * Delete tracking rows whose parent relations no longer exist (orphan cleanup).
     */
    #[Route('/purge-orphans', name: 'admin_import_purge_orphans', methods: ['POST', 'GET'])]
    #[Permission('admin.import.purge_orphans')]
    public function purgeOrphans(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $conn = $em->getConnection();
        $logs = [];

        // Episodes without Season
        $conn->executeStatement('
        DELETE FROM tracking_episode
        WHERE related_season_id NOT IN (SELECT id FROM tracking_season)
    ');
        $logs[] = 'Purged orphaned Episodes.';

        // Seasons without TV
        $conn->executeStatement('
        DELETE FROM tracking_season
        WHERE related_tv_id NOT IN (SELECT id FROM tracking_tv)
    ');
        $logs[] = 'Purged orphaned Seasons.';

        // TV without metadata
        $conn->executeStatement('
        DELETE FROM tracking_tv
        WHERE media_metadata_id NOT IN (SELECT id FROM tracking_item)
    ');
        $logs[] = 'Purged orphaned TV entries.';

        // Movies without metadata
        $conn->executeStatement('
        DELETE FROM tracking_movie
        WHERE media_metadata_id NOT IN (SELECT id FROM tracking_item)
    ');
        $logs[] = 'Purged orphaned Movies.';

        // Metadata without any linked entity
        $conn->executeStatement('
        DELETE FROM tracking_item
        WHERE id NOT IN (SELECT media_metadata_id FROM tracking_movie)
          AND id NOT IN (SELECT media_metadata_id FROM tracking_tv)
          AND id NOT IN (SELECT media_metadata_id FROM tracking_season)
          AND id NOT IN (SELECT media_metadata_id FROM tracking_episode)
    ');
        $logs[] = 'Purged orphaned MediaMetadata.';

        $session->set('admin_import_logs', $logs);

        return $this->json(['status' => 'orphans_purged', 'logs' => $logs]);
    }

    /**
     * Run integrity checks for duplicates, orphans, missing metadata and broken relations.
     */
    #[Route('/validate-integrity', name: 'admin_import_validate_integrity', methods: ['GET'])]
    #[Permission('admin.import.validate_integrity')]
    public function validateIntegrity(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $conn = $em->getConnection();

        $report = [
            'duplicates' => [],
            'orphans' => [],
            'corrupted' => [],
            'missing_metadata' => [],
            'broken_relations' => [],
        ];

        // Duplicate MediaMetadata
        $report['duplicates']['metadata'] = $conn->fetchAllAssociative('
        SELECT media_id, COUNT(*) AS count
        FROM tracking_item
        GROUP BY media_id, media_type, season_number, episode_number
        HAVING COUNT(*) > 1
    ');

        // Orphaned Episodes
        $report['orphans']['episodes'] = $conn->fetchAllAssociative('
        SELECT id FROM tracking_episode
        WHERE related_season_id NOT IN (SELECT id FROM tracking_season)
    ');

        // Orphaned Seasons
        $report['orphans']['seasons'] = $conn->fetchAllAssociative('
        SELECT id FROM tracking_season
        WHERE related_tv_id NOT IN (SELECT id FROM tracking_tv)
    ');

        // Orphaned TV
        $report['orphans']['tv'] = $conn->fetchAllAssociative('
        SELECT id FROM tracking_tv
        WHERE media_metadata_id NOT IN (SELECT id FROM tracking_item)
    ');

        // Orphaned Movies
        $report['orphans']['movies'] = $conn->fetchAllAssociative('
        SELECT id FROM tracking_movie
        WHERE media_metadata_id NOT IN (SELECT id FROM tracking_item)
    ');

        // Metadata missing externalId
        $report['missing_metadata']['externalId'] = $conn->fetchAllAssociative('
        SELECT id, title FROM tracking_item
        WHERE external_id IS NULL
    ');

        // Corrupted Movie → Season/TV relationships
        $report['corrupted']['movie_seasons'] = $conn->fetchAllAssociative("
        SELECT ts.id FROM tracking_season ts
        JOIN tracking_item ti ON ti.id = ts.media_metadata_id
        WHERE ti.media_type = 'movie'
    ");

        $session->set('admin_import_logs', [
            'Integrity validation completed.',
            'Duplicates: '.\count($report['duplicates']['metadata']),
            'Orphans: '.(\count($report['orphans']['episodes']) + \count($report['orphans']['seasons']) + \count($report['orphans']['tv']) + \count($report['orphans']['movies'])),
            'Missing metadata: '.\count($report['missing_metadata']['externalId']),
            'Corrupted: '.\count($report['corrupted']['movie_seasons']),
        ]);

        return $this->json(['status' => 'integrity_validated', 'report' => $report]);
    }

    /**
     * Recompute TV show progress and status from season/episode data.
     */
    #[Route('/recompute-tv-progress', name: 'admin_import_recompute_tv_progress', methods: ['POST'])]
    #[Permission('admin.import.recompute_tv_progress')]
    public function recomputeTvProgress(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $session = $request->getSession();
        $conn = $em->getConnection();
        $logs = [];

        $result = $conn->executeStatement("
            UPDATE tracking_tv tv
            JOIN (
                SELECT 
                    s.related_tv_id,
                    COUNT(e.id) as total_episodes,
                    SUM(CASE WHEN e.end_date IS NOT NULL THEN 1 ELSE 0 END) as watched_episodes,
                    MAX(e.end_date) as last_watched
                FROM tracking_season s
                LEFT JOIN tracking_episode e ON e.related_season_id = s.id
                GROUP BY s.related_tv_id
            ) stats ON stats.related_tv_id = tv.id
            SET tv.progress = CASE 
                WHEN stats.total_episodes > 0 
                THEN ROUND((stats.watched_episodes / stats.total_episodes) * 100)
                ELSE 0 
            END,
            tv.status = CASE 
                WHEN stats.total_episodes > 0 AND stats.watched_episodes >= stats.total_episodes THEN 'Completed'
                WHEN stats.watched_episodes > 0 THEN 'In progress'
                ELSE 'Planning'
            END,
            tv.progressed_at = stats.last_watched
        ");

        $logs[] = "Recomputed TV progress for {$result} shows.";
        $session->set('admin_import_logs', $logs);

        return $this->json(['status' => 'tv_progress_recomputed', 'logs' => $logs]);
    }
}
