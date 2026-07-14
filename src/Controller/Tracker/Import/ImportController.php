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

use App\Service\Import\ImportService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ImportController extends AbstractController
{
    public function __construct(
        private ImportService $import,
        private LoggerInterface $log,
    ) {
    }

    #[Route('/import', name: 'import_records', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || empty($payload['records'])) {
            $this->log->warning('import.invalid_payload', ['payload' => $payload]);

            return $this->json(['error' => 'Invalid payload'], 400);
        }

        $records = $payload['records'];
        $runtimeLogs = [];

        foreach ($records as $record) {
            try {
                $this->import->importSingleRecord($record);

                // Runtime logs (Option C)
                $runtimeLogs[] = [
                    'event' => 'import.dispatched',
                    'record' => $record,
                ];
            } catch (\Throwable $e) {
                $this->log->error('import.error', [
                    'record' => $record,
                    'error' => $e->getMessage(),
                ]);

                $runtimeLogs[] = [
                    'event' => 'import.error',
                    'error' => $e->getMessage(),
                    'record' => $record,
                ];
            }
        }

        return $this->json([
            'status' => 'ok',
            'logs' => $runtimeLogs,
        ]);
    }
}
