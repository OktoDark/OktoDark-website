<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service;

use App\Repository\SettingsRepository;

class ThemeResolver
{
    private string $theme;

    public function __construct(SettingsRepository $settings)
    {
        $this->theme = $settings->findOneBy([])->getTheme() ?? 'grey';
    }

    public function getTheme(): string
    {
        return $this->theme;
    }
}
