<?php

declare(strict_types=1);

namespace Respect\Relational;

use Datetime;
use Respect\Data\NotPersistable;

class Post
{
    public mixed $id = null;

    public mixed $author;

    public string|null $text = null;

    public string|null $title = null;

    public mixed $comment_id;

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
