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
        $settings = $this->settings->getSettings();
        $theme = $this->settings->getTheme();

        // Normalize CDN for filesystem path
        $cdnDomain = str_replace(['https://', 'http://'], '', $settings->getSiteCDN());

        // Normalize CDN for Twig (always absolute URL)
        $cdnUrl = $settings->getSiteCDN();
        if (!str_starts_with($cdnUrl, 'http')) {
            $cdnUrl = 'https://'.$cdnUrl;
        }

        // Auto-detect vhosts root
        $vhostsRoot = dirname($_SERVER['DOCUMENT_ROOT']);

        // Build filesystem path
        $cssFilePath = sprintf(
            '%s/%s/themes/%s/css/style.css',
            $vhostsRoot,
            $cdnDomain,
            $theme
        );

        // Inject normalized CDN back into settings object
        $settings->setSiteCDN($cdnUrl);

        return [
            'settings' => $settings,
            'theme' => $theme,
            'css_version' => file_exists($cssFilePath) ? filemtime($cssFilePath) : time(),
        ];
    }
}
