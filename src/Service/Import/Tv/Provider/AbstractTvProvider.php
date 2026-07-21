<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv\Provider;

abstract class AbstractTvProvider implements TvImportProviderInterface
{
    protected function extractIds(array $ids): array
    {
        return [
            'tmdb' => $ids['tmdb'] ?? null,
            'tvdb' => $ids['tvdb'] ?? null,
            'tvmaze' => $ids['tvmaze'] ?? null,
            'trakt' => $ids['trakt'] ?? null,
        ];
    }
}
