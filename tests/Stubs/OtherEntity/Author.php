<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

class Author
{
    private int $id;

    private string $name;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setId(int|null $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
