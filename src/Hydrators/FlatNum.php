<?php

declare(strict_types=1);

namespace Respect\Relational\Hydrators;

use PDOStatement;
use Respect\Data\Hydrators\Flat;

/** Resolves column names from PDOStatement column metadata for numeric-indexed rows */
final class FlatNum extends Flat
{
    public function __construct(
        private readonly PDOStatement $statement,
    ) {
    }

    protected function resolveColumnName(mixed $reference, mixed $raw): string
    {
        /** @phpstan-ignore offsetAccess.nonOffsetAccessible */
        return $this->statement->getColumnMeta($reference)['name'];
    }
}
