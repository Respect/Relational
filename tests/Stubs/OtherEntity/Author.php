<?php

declare(strict_types=1);

namespace Respect\Relational\OtherEntity;

class Author
{
    private mixed $id = null;

    private mixed $name = null;

    public function getId(): mixed
    {
        return $this->id;
    }

    public function getName(): mixed
    {
        return $this->name;
    }

    public function setId(mixed $id): void
    {
        $this->id = $id;
    }

    public function setName(mixed $name): void
    {
        $this->name = $name;
    }
}
