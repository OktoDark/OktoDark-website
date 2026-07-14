<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie\Provider;

interface MovieImportProviderInterface
{
    /**
     * Parse a raw record and return a normalized movie record.
     * Return null if the record does not represent a movie.
     */
    public function parse(array $row): ?array;
}
