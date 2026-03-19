<?php

declare(strict_types=1);

namespace Respect\Relational;

use function array_fill;
use function array_filter;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_merge;
use function count;
use function current;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function key;
use function preg_replace;
use function strtoupper;

/** Fluent SQL builder with shape-based argument detection */
class Sql
{
    /** Instructions where assoc array values are raw identifiers, not parameterized */
    private const array RAW = ['on', 'select'];

    /**
     * Operators that expand an array value into multiple placeholders.
     * Each entry: [prefix, separator, suffix, minValues, maxValues|null]
     */
    private const array EXPAND = [
        'IN'     => ['(', ', ', ')', 1, null],
        'NOT IN' => ['(', ', ', ')', 1, null],
        'BETWEEN' => ['', ' AND ', '', 2, 2],
    ];

    /** @phpstan-var list<string> */
    private(set) array $query = [];

    /** @phpstan-var list<scalar|null> */
    private(set) array $params = [];

    public function __construct(private readonly bool $raw = false)
    {
    }

    public static function raw(string $expression): static
    {
        $sql = new static(raw: true);
        $sql->query[] = $expression;

        return $sql;
    }

    public function concat(self $sql): static
    {
        $this->query[] = (string) $sql;
        $this->params = array_merge($this->params, $sql->params);

        return $this;
    }

    /**
     * select('a', 'b'), from('t1', 't2'), orderBy('col')
     *
     * @param scalar|array<int|string, scalar|self|list<scalar>>|self ...$items
     */
    private function commaList(string|int|float|bool|array|self ...$items): static
    {
        $this->query[] = $this->formatList(...$items);

        return $this;
    }

    /**
     * insertInto('table', ['col1', 'col2']), createTable('t', [['id', 'INT']])
     *
     * @param list<scalar|self|list<scalar>> $columns
     */
    private function namedList(string $name, array $columns): static
    {
        $this->query[] = $name;
        $this->query[] = '(' . $this->formatList(...$columns) . ')';

        return $this;
    }

    /**
     * on(['table.col' => 'other.col']) — raw identifier pairs, no params
     *
     * @param array<int|string, scalar|self|list<scalar>> $pairs
     */
    private function rawPairs(array $pairs): static
    {
        $parts = [];
        foreach ($pairs as $k => $v) {
            if (!is_scalar($v)) {
                continue;
            }

            $parts[] = $k . ' = ' . $v;
        }

        $this->query[] = implode(' AND ', $parts);

        return $this;
    }

    /**
     * set(['col' => 123, 'other' => Sql::raw('NOW()')]) — parameterized pairs
     *
     * @param array<int|string, scalar|self|list<scalar>> $pairs
     */
    private function paramPairs(array $pairs): static
    {
        $parts = [];
        foreach ($pairs as $k => $v) {
            if ($v instanceof self) {
                $parts[] = $k . ' = ' . $this->absorb($v);
            } else {
                $parts[] = $k . ' = ?';
                $this->params[] = is_scalar($v) ? $v : null;
            }
        }

        $this->query[] = implode(', ', $parts);

        return $this;
    }

    /**
     * values([1, 2, null, Sql::raw('NOW()')]) — parenthesized placeholder list
     *
     * @param list<scalar|self|list<scalar>> $values
     */
    private function valueList(array $values): static
    {
        $placeholders = [];
        foreach ($values as $v) {
            if ($v instanceof self) {
                $placeholders[] = $this->absorb($v);
            } else {
                $placeholders[] = '?';
                $this->params[] = is_scalar($v) ? $v : null;
            }
        }

        $this->query[] = '(' . implode(', ', $placeholders) . ')';

        return $this;
    }

    /**
     * where([['col','=','val'], 'AND', ['col2','IN',[1,2]]]) — triplet conditions
     *
     * Items are either operator strings ('AND', 'OR') or condition arrays:
     *   - array{string, string, scalar|null}   scalar triplet
     *   - array{string, string, self}          subquery triplet
     *   - array{string, string, list<scalar>}  expand triplet (IN, NOT IN, BETWEEN)
     *   - list<...>                            nested group (recursive)
     *
     * @param list<scalar|self|list<scalar>> $items
     */
    private function conditions(array $items): string
    {
        $q = '';
        foreach ($items as $item) {
            if (is_string($item)) {
                $q .= ' ' . strtoupper($item) . ' ';
                continue;
            }

            if (is_array($item) && count($item) === 3 && is_string($item[0]) && is_string($item[1])) {
                $q .= $this->triplet($item[0], $item[1], $item[2]);
                continue;
            }

            if (is_array($item)) {
                $q .= '(' . $this->conditions($item) . ')';
                continue;
            }
        }

        return $q;
    }

    /**
     * ['col', '=', scalar|null|self|list<scalar>] — single condition triplet
     *
     * @param list<scalar>|scalar|self|null $value
     */
    private function triplet(
        string $column,
        string $operator,
        string|int|float|bool|self|array|null $value,
    ): string {
        if ($value === null) {
            return $this->nullTriplet($column, $operator);
        }

        if ($value instanceof self) {
            return $this->subqueryTriplet($column, $operator, $value);
        }

        if (is_array($value)) {
            return $this->expandTriplet($column, $operator, $value);
        }

        return $this->scalarTriplet($column, $operator, $value);
    }

    /** ['col', '=', null] → col IS NULL, ['col', '!=', null] → col IS NOT NULL */
    private function nullTriplet(string $column, string $operator): string
    {
        return match ($operator) {
            '=', '==' => $column . ' IS NULL',
            '!=', '<>' => $column . ' IS NOT NULL',
            default => throw new SqlException(
                'Operator \'' . $operator . '\' does not support null values',
            ),
        };
    }

    /** ['col', '=', 'val'] — simple comparison with placeholder */
    private function scalarTriplet(
        string $column,
        string $operator,
        string|int|float|bool $value,
    ): string {
        $this->params[] = $value;

        return $column . ' ' . $operator . ' ?';
    }

    /** ['col', '=', Sql::select(...)] — comparison against subquery */
    private function subqueryTriplet(string $column, string $operator, self $value): string
    {
        return $column . ' ' . $operator . ' ' . $this->absorb($value);
    }

    /**
     * ['col', 'IN', [1,2,3]], ['col', 'NOT IN', [4,5]], or ['col', 'BETWEEN', [1, 100]]
     *
     * @param list<scalar> $value
     */
    private function expandTriplet(string $column, string $operator, array $value): string
    {
        $op = strtoupper($operator);

        if (!isset(self::EXPAND[$op])) {
            throw new SqlException(
                'Unsupported expand operator \'' . $op . '\', expected: '
                . implode(', ', array_keys(self::EXPAND)),
            );
        }

        [$pre, $sep, $suf, $min, $max] = self::EXPAND[$op];
        $n = count($value);
        if ($n < $min || ($max !== null && $n > $max)) {
            $expected = $max === $min ? (string) $min : $min . '+';

            throw new SqlException(
                $op . ' requires ' . $expected . ' values, got ' . $n,
            );
        }

        $placeholders = array_fill(0, count($value), '?');
        $this->params = array_merge($this->params, $value);

        return $column . ' ' . $op . ' ' . $pre . implode($sep, $placeholders) . $suf;
    }

    private function absorb(self $sql): string
    {
        $this->params = array_merge($this->params, $sql->params);

        return $sql->raw ? (string) $sql : '(' . $sql . ')';
    }

    /** @param array<int|string, scalar|self|list<scalar>> $pair */
    private function alias(array $pair): string
    {
        $value = current($pair);
        if ($value instanceof self) {
            return $this->absorb($value) . ' AS ' . key($pair);
        }

        return (is_scalar($value) ? $value : '') . ' AS ' . key($pair);
    }

    /** @param scalar|list<scalar>|array<int|string, scalar|self|list<scalar>>|self ...$names */
    private function formatList(string|int|float|bool|array|self ...$names): string
    {
        return implode(', ', array_map(
            fn($name) => match (true) {
                $name instanceof self => $this->absorb($name),
                is_array($name) && array_is_list($name) => implode(
                    ' ',
                    array_filter($name, is_scalar(...)),
                ),
                is_array($name) => $this->alias($name),
                default => $name,
            },
            $names,
        ));
    }

    public function __toString(): string
    {
        return implode(' ', $this->query);
    }

    /**
     * @see self::__call()
     *
     * @param array<int, scalar|array<int|string, scalar|self|list<scalar>>|self> $args
     */
    public static function __callStatic(string $name, array $args): static
    {
        return (new static())->__call($name, $args);
    }

    /**
     * Dispatches SQL clauses by detecting argument shapes:
     *   - string, ...                         comma list (select, from, orderBy)
     *   - string, list<string|list<string>>   name + columns (insertInto, createTable)
     *   - array<string, string>               raw pairs (on)
     *   - array<string, scalar|null|self>     parameterized pairs (set)
     *   - list<scalar|null|self>              value list (values)
     *   - list<array{string,string,scalar|null|self|list<scalar>}|string|list<...>>
     *                                         conditions (where, having)
     *
     * @param array<int, scalar|array<int|string, scalar|self|list<scalar>>|self> $args
     */
    public function __call(string $name, array $args): static
    {
        $this->query[] = strtoupper(preg_replace('/[A-Z0-9]+/', ' $0', $name));

        if (empty($args)) {
            return $this;
        }

        if (!is_array($args[0])) {
            if (count($args) > 1 && is_array($args[1]) && array_is_list($args[1])) {
                return $this->namedList((string) $args[0], $args[1]);
            }

            return $this->commaList(...$args);
        }

        if (!array_is_list($args[0])) {
            if (in_array($name, self::RAW)) {
                return $this->rawPairs($args[0]);
            }

            return $this->paramPairs($args[0]);
        }

        if (count($args[0]) < 1 || !is_array($args[0][0])) {
            return $this->valueList($args[0]);
        }

        $this->query[] = $this->conditions($args[0]);

        return $this;
    }
}
