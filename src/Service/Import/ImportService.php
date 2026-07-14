<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import;

use App\Enum\MediaType;
use App\Service\Import\Episode\EpisodeImportMessage;
use App\Service\Import\Movie\MovieImportMessage;
use App\Service\Import\Season\SeasonImportMessage;
use App\Service\Import\Tv\TvImportMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ImportService
{
    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $log,
    ) {
    }

    /**
     * Async‑ready dispatcher: decides which import pipeline to use.
     */
    public function importSingleRecord(array $record): void
    {
        $type = $this->detectType($record);

        $this->log->info('import.dispatch', [
            'type' => $type?->value ?? null,
            'record' => $record,
        ]);

        switch ($type) {
            case MediaType::MOVIE:
                $this->bus->dispatch(new MovieImportMessage($record));
                break;

            case MediaType::TV:
                $this->bus->dispatch(new TvImportMessage($record));
                break;

            case MediaType::EPISODE:
                $this->bus->dispatch(new EpisodeImportMessage($record));
                break;

            case MediaType::SEASON:
                $this->bus->dispatch(new SeasonImportMessage($record));
                break;

            default:
                $this->log->warning('import.unknown_type', [
                    'record' => $record,
                ]);
        }
    }

    private function detectType(array $record): ?MediaType
    {
        $mediaType = $record['type'] ?? $record['media_type'] ?? null;

        if (isset($record['show'])) {
            $mediaType = 'tv';
        }

        if (isset($record['movie'])) {
            $mediaType = 'movie';
        }

        if (isset($record['episode'])) {
            $mediaType = 'episode';
        }

        if (!$mediaType) {
            return null;
        }

        try {
            return MediaType::from(mb_strtolower($mediaType));
        } catch (\ValueError) {
            return null;
        }
    }
}
