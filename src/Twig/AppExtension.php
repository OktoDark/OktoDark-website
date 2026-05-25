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

use Symfony\Component\Intl\Locales;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private array $localeCodes;
    private ?array $locales = null;

    public function __construct(string $locales, private TranslatorInterface $translator)
    {
        $localeCodes = explode('|', $locales);
        sort($localeCodes);
        $this->localeCodes = $localeCodes;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('locales', [$this, 'getLocales']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'agoFilter']),
        ];
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     */
    public function getLocales(): array
    {
        if (null !== $this->locales) {
            return $this->locales;
        }

        $this->locales = [];
        foreach ($this->localeCodes as $localeCode) {
            $this->locales[] = ['code' => $localeCode, 'name' => Locales::getName($localeCode, $localeCode)];
        }

        return $this->locales;
    }

    /**
     * Converts a DateTime object to a human-readable "time ago" string.
     */
    public function agoFilter(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $this->translator->trans('notification.ago.year', ['%count%' => $diff->y], 'notifications');
        }
        if ($diff->m > 0) {
            return $this->translator->trans('notification.ago.month', ['%count%' => $diff->m], 'notifications');
        }
        if ($diff->d > 0) {
            return $this->translator->trans('notification.ago.day', ['%count%' => $diff->d], 'notifications');
        }
        if ($diff->h > 0) {
            return $this->translator->trans('notification.ago.hour', ['%count%' => $diff->h], 'notifications');
        }
        if ($diff->i > 0) {
            return $this->translator->trans('notification.ago.minute', ['%count%' => $diff->i], 'notifications');
        }

        return $this->translator->trans('notification.ago.just_now', [], 'notifications');
    }
}
