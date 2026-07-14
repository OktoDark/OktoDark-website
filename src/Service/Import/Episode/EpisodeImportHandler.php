<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Episode;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EpisodeImportHandler
{
    public function __construct(
        private EpisodeImportService $service,
    ) {
    }

    public function __invoke(EpisodeImportMessage $message): void
    {
        $this->service->import($message->record);
    }
}
