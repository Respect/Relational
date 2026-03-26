<?php

declare(strict_types=1);

namespace Respect\Relational;

class Category
{
    public int $id;

    public string $name;

    public Category $category;
}
