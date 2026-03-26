<?php

declare(strict_types=1);

namespace Respect\Relational;

class Postcomment
{
    public int $id;

    public string $title;

    public string $text;

    public Author $author;
}
