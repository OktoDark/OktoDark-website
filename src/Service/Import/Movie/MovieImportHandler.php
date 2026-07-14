<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Service\Import\Movie;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MovieImportHandler
{
    public function __construct(
        private MovieImportService $service,
    ) {
    }

    public function __invoke(MovieImportMessage $message): void
    {
        $this->service->import($message->record);
    }
}
