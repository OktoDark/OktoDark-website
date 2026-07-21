<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Metadata\Structure;

/**
 * Normalized image set (poster + backdrop + still) shared by every provider.
 */
final class ImageSet
{
    public function __construct(
        public readonly ?string $poster = null,
        public readonly ?string $backdrop = null,
        public readonly ?string $still = null,
    ) {
    }
}
