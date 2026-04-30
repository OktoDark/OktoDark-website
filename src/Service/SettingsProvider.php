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
        if (null !== $this->settings) {
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

        return $this->settings?->getTheme() ?? 'modern';
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

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        if (!$this->settings) {
            return $default;
        }

        // Convert snake_case to CamelCase
        $camelCaseKey = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));

        // Try get method first
        $getMethod = 'get'.$camelCaseKey;
        if (method_exists($this->settings, $getMethod)) {
            return $this->settings->$getMethod();
        }

        // If it's a boolean-like key, try is method
        $isMethod = 'is'.$camelCaseKey;
        if (method_exists($this->settings, $isMethod)) {
            return $this->settings->$isMethod();
        }

        return $default;
    }

    public function isRegistrationEnabled(): bool
    {
        return (bool) $this->get('register_enabled', true);
    }

    public function setRegistrationEnabled(bool $enabled): void
    {
        $this->load();

        if ($this->settings && method_exists($this->settings, 'setRegisterEnabled')) {
            $this->settings->setRegisterEnabled($enabled);
            $this->repo->save($this->settings, true);
        }
    }
}
