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

enum MediaType: string
{
    case TV = 'tv';
    case SEASON = 'season';
    case EPISODE = 'episode';
    case MOVIE = 'movie';
    case ANIME = 'anime';
    case MANGA = 'manga';
    case GAME = 'game';
    case BOOK = 'book';
    case COMIC = 'comic';
    case BOARDGAME = 'boardgame';
}
