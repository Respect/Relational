<?php

declare(strict_types=1);

namespace Respect\Relational;

use function array_merge;
use function array_shift;
use function array_walk_recursive;
use function implode;
use function in_array;
use function is_int;
use function is_numeric;
use function preg_match;
use function preg_replace;
use function rtrim;
use function sprintf;
use function stripos;
use function strtoupper;
use function substr;
use function trim;

class Sql
{
    public const string SQL_OPERATORS = '/\s?(NOT)?\s?(=|==|<>|!=|>|>=|<|<=|LIKE)\s?$/';
    public const string PLACEHOLDER   = '?';

    protected string $query = '';

    /** @var array<int, mixed> */
    protected array $params = [];

    public function __construct(string $rawSql = '', array|null $params = null)
    {
        $this->setQuery($rawSql, $params);
    }

    public static function enclose(mixed $sql): mixed
    {
        if ($sql instanceof self) {
            $sql->query = '(' . trim($sql->query) . ') ';
        } elseif ($sql != '') {
            $sql = '(' . trim($sql) . ') ';
        }

        return $sql;
    }

    /** @return array<int, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function setQuery(string $rawSql, array|null $params = null): static
    {
        $this->query = $rawSql;
        if ($params !== null) {
            $this->params = $params;
        }

        return $this;
    }

    public function appendQuery(mixed $sql, array|null $params = null): static
    {
        $this->query = trim($this->query) . ' ' . $sql;
        if ($sql instanceof self) {
            $this->params = array_merge($this->params, $sql->getParams());
        }

        if ($params !== null) {
            $this->params = array_merge($this->params, $params);
        }

        return $this;
    }

    /** @param array<mixed> $parts */
    protected function preBuild(string $operation, array $parts): static
    {
        $raw   = ($operation == 'select' || $operation == 'on');
        $parts = $this->normalizeParts($parts, $raw);
        if (empty($parts) && !in_array($operation, ['asc', 'desc', '_'], true)) {
            return $this;
        }

        if ($operation == 'cond') {
            // condition list
            return $this->build('and', $parts);
        }

        $this->buildOperation($operation);
        $operation = trim($operation, '_');

        return $this->build($operation, $parts);
    }

    /** @param array<mixed> $parts */
    protected function build(string $operation, array $parts): static
    {
        return match ($operation) {
            'select' => $this->buildAliases($parts),
            'and', 'having', 'where', 'between' => $this->buildKeyValues($parts, '%s ', ' AND '),
            'or' => $this->buildKeyValues($parts, '%s ', ' OR '),
            'set' => $this->buildKeyValues($parts),
            'on' => $this->buildComparators($parts, '%s ', ' AND '),
            'in', 'values' => $this->buildValuesList($parts),
            'alterTable' => $this->buildAlterTable($parts),
            'createTable', 'insertInto', 'replaceInto' => $this->buildCreate($parts),
            default => $this->buildParts($parts),
        };
    }

    /** @param array<mixed> $parts */
    protected function buildKeyValues(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = (string) $part;
            } else {
                $value = $part instanceof self ? (string) $part : self::PLACEHOLDER;
                if (preg_match(self::SQL_OPERATORS, $key) > 0) {
                    $parts[$key] = $key . ' ' . $value;
                } else {
                    $parts[$key] = $key . ' = ' . $value;
                }
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    /** @param array<mixed> $parts */
    protected function buildComparators(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = (string) $part;
            } else {
                $parts[$key] = $key . ' = ' . $part;
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    /** @param array<mixed> $parts */
    protected function buildAliases(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = (string) $part;
            } else {
                $parts[$key] = $part . ' AS ' . $key;
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    /** @param array<mixed> $parts */
    protected function buildValuesList(array $parts): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key) || $part instanceof self) {
                $parts[$key] = (string) $part;
            } else {
                $parts[$key] = self::PLACEHOLDER;
            }
        }

        return $this->buildParts($parts, '(%s) ', ', ');
    }

    protected function buildOperation(string $operation): void
    {
        $command = strtoupper(preg_replace('/[A-Z0-9]+/', ' $0', $operation));
        if ($command == '_') {
            $this->query = rtrim($this->query) . ') ';
        } elseif ($command[0] == '_') {
            $this->query .= '(' . trim($command, '_ ') . ' ';
        } elseif (substr($command, -1) == '_') {
            $this->query .= trim($command, '_ ') . ' (';
        } else {
            $this->query .= trim($command) . ' ';
        }
    }

    /** @param array<mixed> $parts */
    protected function buildFirstPart(array &$parts): void
    {
        $this->query .= array_shift($parts) . ' ';
    }

    /** @param array<mixed> $parts */
    protected function buildParts(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        if (!empty($parts)) {
            $this->query .= sprintf($format, implode($partSeparator, $parts));
        }

        return $this;
    }

    /**
     * @param array<mixed> $parts
     *
     * @return array<mixed>
     */
    protected function normalizeParts(array $parts, bool $raw = false): array
    {
        $params = & $this->params;
        $newParts = [];

        array_walk_recursive($parts, static function ($value, $key) use (&$newParts, &$params, &$raw): void {
            if ($value instanceof Sql) {
                $params = array_merge($params, $value->getParams());
                if (stripos((string) $value, '(') !== 0) {
                    $value = Sql::enclose($value);
                }

                $newParts[$key] = $value;
            } elseif ($raw) {
                $newParts[$key] = $value;
            } elseif (is_int($key)) {
                $newParts[] = $value;
            } else {
                $newParts[$key] = $key;
                $params[] = $value;
            }
        });

        return $newParts;
    }

    /** @param array<mixed> $parts */
    private function buildAlterTable(array $parts): static
    {
        $this->buildFirstPart($parts);

        return $this->buildParts($parts, '%s ');
    }

    /** @param array<mixed> $parts */
    private function buildCreate(array $parts): static
    {
        $this->params = [];
        $this->buildFirstPart($parts);

        return $this->buildParts($parts, '(%s) ');
    }

    /** @param array<mixed> $parts */
    public static function __callStatic(string $operation, array $parts): static
    {
        $sql = new static();

        return $sql->$operation(...$parts);
    }

    /** @param array<mixed> $parts */
    public function __call(string $operation, array $parts): static
    {
        return $this->preBuild($operation, $parts);
    }

    public function __toString(): string
    {
        return rtrim($this->query);
    }
}
