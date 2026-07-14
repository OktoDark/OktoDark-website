<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Enum;

enum Source: string
{
    case TRAKT = 'trakt';
    case TMDB = 'tmdb';
    case MAL = 'mal';
    case MANGAUPDATES = 'mangaupdates';
    case IGDB = 'igdb';
    case OPENLIBRARY = 'openlibrary';
    case HARDCOVER = 'hardcover';
    case COMICVINE = 'comicvine';
    case BGG = 'bgg';
    case MANUAL = 'manual';
    case ANILIST = 'anilist';
    case TVMAZE = 'tvmaze';
    case TVTIME = 'tvtime';
    case LETTERBOXD = 'letterboxd';
    case SIMKL = 'simkl';
    case CUSTOM = 'custom';
    case UNKNOWN = 'unknown';
}
