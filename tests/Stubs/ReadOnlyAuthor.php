<?php

declare(strict_types=1);

namespace Respect\Relational;

final readonly class ReadOnlyAuthor
{
    public function __construct(
        public int $id,
        public string $name,
        public string|null $bio = null,
    ) {
    }
}
