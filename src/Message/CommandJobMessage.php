<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Message;

final class CommandJobMessage
{
    public function __construct(
        private string $jobId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
