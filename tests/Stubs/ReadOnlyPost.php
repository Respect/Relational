<?php

declare(strict_types=1);

namespace Respect\Relational;

final readonly class ReadOnlyPost
{
    public function __construct(
        public int $id,
        public string $title,
        public string|null $text = null,
        public ReadOnlyAuthor|null $readOnlyAuthor = null,
    ) {
    }
}
