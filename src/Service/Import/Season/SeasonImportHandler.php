<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Season;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SeasonImportHandler
{
    public function __construct(
        private SeasonImportService $service,
    ) {
    }

    public function __invoke(SeasonImportMessage $message): void
    {
        $this->service->import($message->record);
    }
}
