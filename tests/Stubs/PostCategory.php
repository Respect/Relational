<?php

declare(strict_types=1);

namespace Respect\Relational;

class PostCategory
{
    public int $id;

    public Post $post;

    public Category $category;
}
