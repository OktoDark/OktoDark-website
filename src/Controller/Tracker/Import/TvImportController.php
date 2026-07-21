<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Controller\Tracker\Import;

use App\Security\Attribute\Permission;
use App\Service\Import\Tv\TvImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class TvImportController extends AbstractController
{
    private const SOURCES = ['tvtime', 'trakt', 'simkl', 'generic'];

    private const SOURCE_HINTS = [
        'tvtime' => [
            'title' => 'TV Time',
            'description' => 'Import from TV Time CSV export (tracking-prod-records*.csv).',
            'accept' => '.csv',
            'extensions' => ['csv'],
        ],
        'trakt' => [
            'title' => 'Trakt',
            'description' => 'Import from Trakt JSON or CSV export.',
            'accept' => '.json,.csv',
            'extensions' => ['json', 'csv'],
        ],
        'simkl' => [
            'title' => 'Simkl',
            'description' => 'Import from Simkl JSON export.',
            'accept' => '.json',
            'extensions' => ['json'],
        ],
        'generic' => [
            'title' => 'Generic CSV / JSON',
            'description' => 'Import from a generic CSV or JSON file with title, season, episode columns.',
            'accept' => '.csv,.json',
            'extensions' => ['csv', 'json'],
        ],
    ];

    #[Route('/import/tv', name: 'app_import_tv', methods: ['GET'])]
    #[Permission('tracker.import.tv')]
    public function index(Request $request): Response
    {
        return $this->render('@theme/tracker/import/tv.html.twig', [
            'logs' => $request->getSession()->get('import_logs', []),
            'source' => null,
            'sourceHints' => self::SOURCE_HINTS,
        ]);
    }

    #[Route('/import/tv/{source}', name: 'app_import_tv_source', methods: ['GET'], requirements: ['source' => 'tvtime|trakt|simkl|generic'])]
    #[Permission('tracker.import.tv')]
    public function indexSource(Request $request, string $source): Response
    {
        return $this->render('@theme/tracker/import/tv.html.twig', [
            'logs' => $request->getSession()->get('import_logs', []),
            'source' => $source,
            'sourceHints' => self::SOURCE_HINTS,
        ]);
    }

    #[Route('/import/tv', name: 'app_import_tv_upload', methods: ['POST'])]
    #[Permission('tracker.import.tv')]
    public function upload(Request $request, TvImportService $importer): Response
    {
        return $this->handleUpload($request, $importer, null);
    }

    #[Route('/import/tv/{source}', name: 'app_import_tv_source_upload', methods: ['POST'], requirements: ['source' => 'tvtime|trakt|simkl|generic'])]
    #[Permission('tracker.import.tv')]
    public function uploadSource(Request $request, string $source, TvImportService $importer): Response
    {
        return $this->handleUpload($request, $importer, $source);
    }

    private function handleUpload(Request $request, TvImportService $importer, ?string $source): Response
    {
        /** @var UploadedFile[] $files */
        $files = $request->files->all()['files'] ?? [$request->files->get('file')];

        if (!$files || !\count(array_filter($files))) {
            return $this->json(['error' => 'No files uploaded'], 400);
        }

        if ($source) {
            $hints = self::SOURCE_HINTS[$source] ?? null;
            if ($hints) {
                $allowed = $hints['extensions'];
                foreach ($files as $file) {
                    if ($file instanceof UploadedFile) {
                        $ext = mb_strtolower($file->getClientOriginalExtension());
                        if (!\in_array($ext, $allowed, true)) {
                            return $this->json([
                                'error' => "Invalid file type for {$hints['title']}. Accepted: " . implode(', ', $allowed),
                            ], 400);
                        }
                    }
                }
            }
        }

        $session = $request->getSession();
        $user = $this->getUser();

        $response = new StreamedResponse(static function () use ($importer, $files, $user, $session, $source): void {
            $session->save();

            @ini_set('max_execution_time', '0');

            $lastFlush = microtime(true);

            $emit = static function (array $event) use (&$lastFlush): void {
                $now = microtime(true);

                if ($now - $lastFlush >= 15) {
                    echo ": keep-alive\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();

                    $lastFlush = $now;
                }

                echo 'data: '.json_encode($event)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();

                $lastFlush = microtime(true);
            };

            if ($source) {
                $emit(['type' => 'log', 'level' => 'info', 'message' => "Importing from source: " . self::SOURCE_HINTS[$source]['title']]);
            }

            $result = $importer->importStreamed($files, $user, $emit);

            try {
                $bagKey = 'attributes';
                if (\class_exists(\Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag::class)) {
                    $bagKey = (new \Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag())->getStorageKey();
                }
                $_SESSION[$bagKey]['import_logs'] = $result['logs'];

                $handler = $session->getStorage()->getSaveHandler();
                $handler->write($session->getId(), serialize($_SESSION));
            } catch (\Throwable $e) {
                // Non-fatal: the browser already received the logs live via SSE.
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
