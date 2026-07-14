<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Message;

class RefreshStatsMessage
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
