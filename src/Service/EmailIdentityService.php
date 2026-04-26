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

class EmailIdentityService
{
    public function __construct(
        private string $jobs,
        private string $contact,
        private string $noreply,
    ) {
    }

    public function jobs(): string
    {
        return $this->jobs;
    }

    public function contact(): string
    {
        return $this->contact;
    }

    public function noreply(): string
    {
        return $this->noreply;
    }
}
