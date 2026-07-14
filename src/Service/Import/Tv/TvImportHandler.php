<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Tv;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TvImportHandler
{
    public function __construct(
        private TvImportService $service,
    ) {
    }

    public function __invoke(TvImportMessage $message): void
    {
        $this->service->import($message->record);
    }
}
