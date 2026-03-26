<?php

declare(strict_types=1);

namespace Respect\Relational;

use Datetime;
use Respect\Data\NotPersistable;

class Post
{
    public int $id;

    public Author $author;

    public string $text;

    public string $title;

    public Comment $comment;

    #[NotPersistable]
    private string $datetime = '';

    public function setDatetime(Datetime $datetime): void
    {
        $this->datetime = $datetime->format('Y-m-d H:i:s');
    }

    public function getDatetime(): Datetime
    {
        return new Datetime($this->datetime);
    }
}
