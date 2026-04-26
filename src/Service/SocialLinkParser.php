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

class SocialLinkParser
{
    private array $map;

    public function __construct(array $social_base_urls)
    {
        $this->map = $social_base_urls;
    }

    /**
     * Build a URL when the network is known (user selected it).
     */
    public function build(string $network, string $username): array
    {
        $username = mb_ltrim($username, '@/');

        // Custom link → return raw
        if ('custom' === $network) {
            return [
                'network' => 'custom',
                'url' => $username,
            ];
        }

        // Known network → build URL
        if (isset($this->map[$network])) {
            return [
                'network' => $network,
                'url' => $this->map[$network].$username,
            ];
        }

        // Fallback
        return [
            'network' => 'custom',
            'url' => $username,
        ];
    }

    /**
     * Auto-detect network from input (URL or username).
     */
    public function detectAndBuild(string $input): array
    {
        $input = mb_trim($input);

        // Full URL
        if (str_contains($input, 'http')) {
            return $this->detectFromUrl($input);
        }

        // Username → guess network
        foreach ($this->map as $network => $base) {
            if ($this->matches($network, $input)) {
                return [
                    'network' => $network,
                    'url' => $base.mb_ltrim($input, '@/'),
                ];
            }
        }

        // Fallback
        return [
            'network' => 'custom',
            'url' => $input,
        ];
    }

    private function detectFromUrl(string $url): array
    {
        foreach ($this->map as $network => $base) {
            if (str_contains($url, $network)) {
                return [
                    'network' => $network,
                    'url' => $url,
                ];
            }
        }

        return ['network' => 'custom', 'url' => $url];
    }

    private function matches(string $network, string $input): bool
    {
        return match ($network) {
            'x', 'twitter' => preg_match('/^[A-Za-z0-9_]{3,15}$/', $input),
            'github' => preg_match('/^[A-Za-z0-9-]{1,39}$/', $input),
            'instagram' => preg_match('/^[A-Za-z0-9._]{1,30}$/', $input),
            'tiktok' => str_starts_with($input, '@'),
            default => false,
        };
    }
}
