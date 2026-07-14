<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Enum;

enum UserMessageLevel: string
{
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
    case INFO = 'info';
}
