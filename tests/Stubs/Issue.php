<?php

declare(strict_types=1);

namespace Respect\Relational;

class Issue
{
    public int $id;

    public string|null $type = null;

    public string|null $title = null;
}
