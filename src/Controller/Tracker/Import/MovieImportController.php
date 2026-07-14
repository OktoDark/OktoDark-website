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

use App\Entity\User;
use App\Service\Import\Movie\GlobalMovieImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MovieImportController extends AbstractController
{
    public function __construct(
        private GlobalMovieImporter $globalImporter,
    ) {
    }

    #[Route('/import/movies', name: 'app_import_movies', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $session = $request->getSession();
            $logs = $session->get('import_logs', []);

            /** @var UploadedFile[] $files */
            $files = $request->files->all()['files'] ?? [$request->files->get('file')];

            if (!$files || !\count(array_filter($files))) {
                return $this->json(['error' => 'No files uploaded'], 400);
            }

            $totalImported = 0;
            $totalErrors = 0;
            $processedFiles = [];

            /** @var User $user */
            $user = $this->getUser();

            foreach ($files as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $filename = time().'_'.$file->getClientOriginalName();
                $processedFiles[] = $filename;

                try {
                    $result = $this->globalImporter->importFile($file, $user);
                    $totalImported += $result['imported'] ?? 0;
                    $logs[] = "Imported {$result['imported']} movies from {$filename}.";
                } catch (\Exception $e) {
                    ++$totalErrors;
                    $logs[] = "Error importing {$filename}: ".$e->getMessage();
                }
            }

            $session->set('import_logs', $logs);

            return $this->json([
                'imported' => $totalImported,
                'skipped' => 0,
                'errors' => $totalErrors,
                'files' => $processedFiles,
            ]);
        }

        $session = $request->getSession();
        $logs = $session->get('import_logs', []);

        return $this->render('@theme/tracker/import/movies.html.twig', [
            'logs' => $logs,
        ]);
    }
}
