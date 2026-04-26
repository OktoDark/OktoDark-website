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

use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $discordWebhookUrl = '', // Can be configured in services.yaml
    ) {
    }

    public function notify(string $event, array $data): void
    {
        if (empty($this->discordWebhookUrl)) {
            return;
        }

        // Basic payload for Discord
        $payload = [
            'content' => sprintf('**Forum Event: %s**', ucfirst(str_replace('_', ' ', $event))),
            'embeds' => [[
                'title' => $data['title'] ?? 'New Activity',
                'description' => $data['summary'] ?? '',
                'url' => $data['url'] ?? '',
                'color' => 5814783, // OktoDark Green
                'footer' => ['text' => 'OktoDark Forum System'],
            ]],
        ];

        try {
            $this->httpClient->request('POST', $this->discordWebhookUrl, [
                'json' => $payload,
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the app
        }
    }
}
