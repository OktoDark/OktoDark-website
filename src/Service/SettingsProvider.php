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

class SettingsProvider
{
    private $settings;

    public function __construct(SettingsRepository $repo)
    {
        // Load settings once
        $this->settings = $repo->findOneBy([]);
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function getTheme(): string
    {
        return $this->settings->getTheme() ?? 'grey';
    }

    public function getSiteName(): string
    {
        return $this->settings->getSiteName();
    }

    public function getCDN(): string
    {
        return $this->settings->getSiteCDN();
    }
}

