<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Event;

use App\Entity\Comment;
use Symfony\Contracts\EventDispatcher\Event;

class CommentCreatedEvent extends Event
{
    public function __construct(
        protected Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}
