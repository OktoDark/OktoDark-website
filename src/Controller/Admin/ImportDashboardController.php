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
use App\Service\TmdbService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    // #[Permission('admin.import.dashboard', group: 'Admin', label: 'Import Dashboard')]
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $logs = $session->get('admin_import_logs', []);

        return $this->render('@theme/admin/import/dashboard.html.twig', [
            'logs' => $logs,
        ]);
    }

    /**
     * Remove movie-typed season and TV tracking rows to clean up stale data.
     */
    #[Route('/cleanup', name: 'admin_import_cleanup', methods: ['POST'])]
    // #[Permission('admin.import.cleanup', group: 'Admin', label: 'Import Cleanup')]
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
    #[Route('/remove-duplicates', name: 'admin_import_remove_duplicates', methods: ['POST'])]
    // #[Permission('admin.import.remove_duplicates', group: 'Admin', label: 'Remove Duplicates')]
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
    // #[Permission('admin.import.rebuild_metadata', group: 'Admin', label: 'Rebuild Metadata')]
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
     * Delete tracking rows whose parent relations no longer exist (orphan cleanup).
     */
    #[Route('/purge-orphans', name: 'admin_import_purge_orphans', methods: ['POST'])]
    // #[Permission('admin.import.purge_orphans', group: 'Admin', label: 'Purge Orphans')]
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
    // #[Permission('admin.import.validate_integrity', group: 'Admin', label: 'Validate Integrity')]
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
}
