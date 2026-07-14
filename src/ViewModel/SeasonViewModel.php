<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\ViewModel;

class SeasonViewModel
{
    public string $title;
    public ?string $coverUrl;
    public ?string $releaseDate;
    public int $watchedEpisodes;
    public int $totalEpisodes;
    public string $statusLabel;
    public string $statusClass;
}


