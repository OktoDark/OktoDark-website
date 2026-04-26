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

class DeviceParserService
{
    public function parse(string $ua): array
    {
        $browser = $this->detectBrowser($ua);
        $os = $this->detectOS($ua);

        return [
            'browser' => $browser,
            'os' => $os,
            'icon' => $this->getIcon($browser, $os),
            'label' => "$browser on $os",
        ];
    }

    private function detectBrowser(string $ua): string
    {
        $ua = mb_strtolower($ua);

        return match (true) {
            str_contains($ua, 'edg') => 'Edge',
            str_contains($ua, 'chrome') => 'Chrome',
            str_contains($ua, 'firefox') => 'Firefox',
            str_contains($ua, 'safari') => 'Safari',
            str_contains($ua, 'opr')
            || str_contains($ua, 'opera') => 'Opera',
            default => 'Unknown Browser',
        };
    }

    private function detectOS(string $ua): string
    {
        $ua = mb_strtolower($ua);

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') => 'macOS',
            str_contains($ua, 'iphone') => 'iPhone',
            str_contains($ua, 'ipad') => 'iPad',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Unknown OS',
        };
    }

    private function getIcon(string $browser, string $os): string
    {
        return match (true) {
            // Browsers
            'Chrome' === $browser => 'fa-brands fa-chrome',
            'Firefox' === $browser => 'fa-brands fa-firefox-browser',
            'Safari' === $browser => 'fa-brands fa-safari',
            'Edge' === $browser => 'fa-brands fa-edge',
            'Opera' === $browser => 'fa-brands fa-opera',

            // OS
            'Windows' === $os => 'fa-brands fa-windows',
            'macOS' === $os => 'fa-brands fa-apple',
            'iPhone' === $os => 'fa-solid fa-mobile-screen',
            'Android' === $os => 'fa-brands fa-android',
            'Linux' === $os => 'fa-brands fa-linux',

            default => 'fa-solid fa-desktop',
        };
    }
}
