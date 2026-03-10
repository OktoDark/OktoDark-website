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
use Doctrine\DBAL\Exception\TableNotFoundException;

class SettingsProvider
{
    private ?object $settings = null;

    public function __construct(private SettingsRepository $repo)
    {
    }

    private function load(): void
    {
        if ($this->settings !== null) {
            return;
        }

        try {
            $this->settings = $this->repo->findOneBy([]);
        } catch (TableNotFoundException) {
            // Happens in CI or first install
            $this->settings = null;
        }
    }

    public function getSettings(): ?object
    {
        $this->load();
        return $this->settings;
    }

    public function getTheme(): string
    {
        $this->load();
        return $this->settings?->getTheme() ?? 'grey';
    }

    public function getSiteName(): string
    {
        $this->load();
        return $this->settings?->getSiteName() ?? 'OktoDark';
    }

    public function getCDN(): string
    {
        $this->load();
        return $this->settings?->getSiteCDN() ?? '';
    }
}
