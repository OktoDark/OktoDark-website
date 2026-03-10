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

class ThemeResolver
{
    private ?string $theme = null;

    public function __construct(private SettingsRepository $repo)
    {
    }

    private function load(): void
    {
        if (null !== $this->theme) {
            return;
        }

        try {
            $settings = $this->repo->findOneBy([]);
            $this->theme = $settings?->getTheme() ?? 'grey';
        } catch (TableNotFoundException) {
            $this->theme = 'grey';
        }
    }

    public function getTheme(): string
    {
        $this->load();

        return $this->theme;
    }
}
