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

use App\Service\SettingsProvider;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class SettingsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private SettingsProvider $settings,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'settings' => $this->settings->getSettings(),
            'theme' => $this->settings->getTheme(),
        ];
    }
}
