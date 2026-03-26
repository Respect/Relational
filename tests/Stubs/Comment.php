<?php

declare(strict_types=1);

namespace Respect\Relational;

use Datetime;

class Comment
{
    public int $id;

    public Post $post;

    public string $text;

    private string|null $datetime = null;

    public function setDatetime(Datetime $datetime): void
    {
        $this->datetime = $datetime->format('Y-m-d H:i:s');
    }

    public function getDatetime(): Datetime
    {
        return new Datetime($this->datetime);
    }
}
