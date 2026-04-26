<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CountryFlagExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('country_flag', [$this, 'countryFlag']),
        ];
    }

    public function countryFlag(?string $code): string
    {
        if (!$code || 2 !== mb_strlen($code)) {
            return '🏳️'; // fallback
        }

        $code = mb_strtoupper($code);

        // Convert ASCII A-Z to regional indicator symbols
        $offset = 127397;

        return mb_chr(\ord($code[0]) + $offset, 'UTF-8')
            .mb_chr(\ord($code[1]) + $offset, 'UTF-8');
    }
}
