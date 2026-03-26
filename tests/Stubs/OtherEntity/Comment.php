<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

use DomainException;

class Comment
{
    public int $id;

    public Post $post;

    public string $text;

    public string $datetime;

    public function __construct()
    {
        throw new DomainException('Exception from __construct');
    }
}
