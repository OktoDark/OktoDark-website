<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Episode;

final class EpisodeImportMessage
{
    public function __construct(
        public readonly array $record,
    ) {
    }
}
