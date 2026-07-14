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

enum WatchStatus: string
{
    case PLANNING = 'Planning';
    case IN_PROGRESS = 'In progress';
    case COMPLETED = 'Completed';
    case PAUSED = 'Paused';
    case DROPPED = 'Dropped';
    case ON_HOLD = 'On hold';

    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'Planning',
            self::IN_PROGRESS => 'Watching',
            self::COMPLETED => 'Completed',
            self::PAUSED => 'Paused',
            self::DROPPED => 'Dropped',
            self::ON_HOLD => 'On hold',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::PLANNING => 'badge-secondary',
            self::IN_PROGRESS => 'badge-primary',
            self::COMPLETED => 'badge-success',
            self::PAUSED => 'badge-warning',
            self::DROPPED => 'badge-danger',
            self::ON_HOLD => 'badge-info',
        };
    }
}
