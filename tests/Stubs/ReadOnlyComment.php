<?php

declare(strict_types=1);

namespace Respect\Relational;

final readonly class ReadOnlyComment
{
    public function __construct(
        public int $id,
        public string|null $text = null,
        public ReadOnlyPost|null $readOnlyPost = null,
    ) {
    }
}
