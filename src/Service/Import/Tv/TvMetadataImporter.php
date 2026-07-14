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

use App\Entity\MediaMetadata;
use App\Enum\MediaType;
use App\Enum\Source;

class TvMetadataImporter
{
    public function parse(array $record): ?array
    {
        if (!isset($record['show'])) {
            return null;
        }

        $show = $record['show'];

        return [
            'mediaType' => MediaType::TV,
            'title' => $show['title'] ?? 'Unknown',
            'year' => $show['year'] ?? null,
            'source' => Source::TRAKT,
            'ids' => $show['ids'] ?? [],
            'user' => $record['user'] ?? null,
        ];
    }

    public function apply(MediaMetadata $meta, array $parsed): void
    {
        $meta->setTitle($parsed['title']);
        $meta->setSource($parsed['source']);
    }
}

