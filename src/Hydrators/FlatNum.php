<?php

declare(strict_types=1);

namespace Respect\Relational\Hydrators;

use PDOStatement;
use Respect\Data\Hydrators\Flat;

use function is_int;

/** Resolves column names from PDOStatement column metadata for numeric-indexed rows */
final class FlatNum extends Flat
{
    /** @var array<int, array<string, mixed>> */
    private array $metaCache = [];

    public function __construct(
        private readonly PDOStatement $statement,
    ) {
    }

    protected function resolveColumnName(mixed $reference, mixed $raw): string
    {
        return $this->columnMeta($reference)['name'];
    }

    protected function isEntityBoundary(mixed $col, mixed $raw): bool
    {
        if (!is_int($col) || $col <= 0) {
            return false;
        }

        $currentTable = $this->columnMeta($col)['table'] ?? '';
        $previousTable = $this->columnMeta($col - 1)['table'] ?? '';

        return $currentTable !== '' && $previousTable !== '' && $currentTable !== $previousTable;
    }

    /** @return array<string, mixed> */
    private function columnMeta(int $col): array
    {
        return $this->metaCache[$col] ??= $this->statement->getColumnMeta($col) ?: [];
    }
}
