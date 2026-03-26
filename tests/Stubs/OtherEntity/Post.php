<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

class Post
{
    private int $id;

    private Author $author;

    private string $title;

    private string $text;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setId(int|null $id): void
    {
        $this->id = $id;
    }

    public function setAuthor(Author $author): void
    {
        $this->author = $author;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }
}
