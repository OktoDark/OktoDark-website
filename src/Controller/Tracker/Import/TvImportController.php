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

use App\Service\Import\Tv\TvImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class TvImportController extends AbstractController
{
    #[Route('/import/tv', name: 'app_import_tv', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('@theme/tracker/import/tv.html.twig', [
            'logs' => $request->getSession()->get('import_logs', []),
        ]);
    }

    #[Route('/import/tv', name: 'app_import_tv_upload', methods: ['POST'])]
    public function upload(Request $request, TvImportService $importer): Response
    {
        /** @var UploadedFile[] $files */
        $files = $request->files->all()['files'] ?? [$request->files->get('file')];

        if (!$files || !count(array_filter($files))) {
            return $this->json(['error' => 'No files uploaded'], 400);
        }

        $session = $request->getSession();
        $user = $this->getUser();

        $response = new StreamedResponse(function () use ($importer, $files, $user, $session): void {
            // Release the session lock immediately so other requests are not blocked
            // by this long-running import, then keep the connection open for streaming.
            $session->save();

            @ini_set('max_execution_time', '0');

            $emit = static function (array $event): void {
                echo 'data: '.json_encode($event)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            $result = $importer->importStreamed($files, $user, $emit);

            $session->set('import_logs', $result['logs']);
            $session->save();
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
