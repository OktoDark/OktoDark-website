<?php

/*
 * Copyright (c) 2013 - 2020 OktoDark Studios
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
    protected $comment;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}
