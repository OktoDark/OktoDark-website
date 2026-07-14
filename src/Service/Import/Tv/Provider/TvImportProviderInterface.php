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

interface TvImportProviderInterface
{
    /**
     * Parse provider-specific record into unified TV import structure.
     *
     * Must return:
     * [
     *   'title' => string,
     *   'ids' => ['tmdb' => ?, 'tvmaze' => ?, 'trakt' => ?, 'imdb' => ?],
     *   'year' => int|null,
     *   'user' => User,
     *   'season' => int|null,
     *   'episode' => int|null,
     *   'status' => string|null,
     *   'rating' => float|null,
     *   'lastWatchedAt' => \DateTime|null,
     * ]
     */
    public function parse(array $record, $user): ?array;

    /**
     * Detect if this provider can parse the given record.
     */
    public function supports(array $record): bool;
}
