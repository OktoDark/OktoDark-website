<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SocialExtension extends AbstractExtension
{
    private array $baseUrls;
    private array $icons;
    private array $colors;

    public function __construct(array $social_base_urls, array $social_icons, array $social_colors)
    {
        $this->baseUrls = $social_base_urls;
        $this->icons = $social_icons;
        $this->colors = $social_colors;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('social_url', [$this, 'buildUrl']),
            new TwigFunction('social_color', [$this, 'getColor']),
            new TwigFunction('social_label', [$this, 'getLabel']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('icon', [$this, 'getIcon']),
            new TwigFilter('social_detect', [$this, 'detectNetwork']),
        ];
    }

    public function detectNetwork(string $url): string
    {
        foreach ($this->baseUrls as $network => $base) {
            if (str_contains($url, $network)) {
                return $network;
            }
        }

        return 'custom';
    }

    public function buildUrl(string $network, string $username): string
    {
        $username = ltrim($username, '@/');

        if ('custom' === $network) {
            return $username;
        }

        return ($this->baseUrls[$network] ?? '').$username;
    }

    public function getIcon(string $network): string
    {
        return $this->icons[$network] ?? $this->icons['custom'];
    }

    public function getColor(string $network): string
    {
        return $this->colors[$network] ?? '#999';
    }

    public function getLabel(string $network): string
    {
        return ucfirst($network);
    }
}
