<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

use Respect\Data\NotPersistable;

class Post
{
    private mixed $id = null;

    private mixed $author_id = null;

    #[NotPersistable]
    private mixed $author = null;

    private mixed $title = null;

    private mixed $text = null;

    public function getTitle(): mixed
    {
        return $this->title;
    }

    public function setTitle(mixed $title): void
    {
        $this->title = $title;
    }

    public function getId(): mixed
    {
        return $this->id;
    }

    public function getAuthorId(): mixed
    {
        return $this->author_id;
    }

    public function getAuthor(): mixed
    {
        return $this->author;
    }

    public function getText(): mixed
    {
        return $this->text;
    }

    public function setId(mixed $id): void
    {
        $this->id = $id;
    }

    public function setAuthor(Author $author): void
    {
        $this->author_id = $author;
    }

    public function setText(mixed $text): void
    {
        $this->text = $text;
    }
}
