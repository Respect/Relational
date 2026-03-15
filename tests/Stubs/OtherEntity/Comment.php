<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

use DomainException;

class Comment
{
    public int|null $id = null;

    public int|null $post_id = null;

    public string|null $text = null;

    public string|null $datetime = null;

    public function __construct()
    {
        throw new DomainException('Exception from __construct');
    }
}
