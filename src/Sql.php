<?php

declare(strict_types=1);

namespace Respect\Relational;

class Sql
{
    const SQL_OPERATORS = '/\s?(NOT)?\s?(=|==|<>|!=|>|>=|<|<=|LIKE)\s?$/';
    const PLACEHOLDER   = '?';

    protected string $query = '';
    protected array $params = [];

    public static function __callStatic(string $operation, array $parts): static
    {
        $sql = new static();

        return $sql->$operation(...$parts);
    }

    public static function enclose(mixed $sql): mixed
    {
        if ($sql instanceof self) {
            $sql->query = '('.trim($sql->query).') ';
        } elseif ($sql != '') {
            $sql = '('.trim($sql).') ';
        }

        return $sql;
    }

    public function __call(string $operation, array $parts): static
    {
        return $this->preBuild($operation, $parts);
    }

    public function __construct(string $rawSql = '', array|null $params = null)
    {
        $this->setQuery($rawSql, $params);
    }

    public function __toString(): string
    {
        return rtrim($this->query);
    }

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
        $this->query = trim($this->query)." $sql";
        if ($sql instanceof self) {
            $this->params = array_merge($this->params, $sql->getParams());
        }
        if ($params !== null) {
            $this->params = array_merge($this->params, $params);
        }

        return $this;
    }

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

    private function buildAlterTable(array $parts): static
    {
        $this->buildFirstPart($parts);

        return $this->buildParts($parts, '%s ');
    }

    private function buildCreate(array $parts): static
    {
        $this->params = [];
        $this->buildFirstPart($parts);

        return $this->buildParts($parts, '(%s) ');
    }

    protected function buildKeyValues(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = "$part";
            } else {
                $value = ($part instanceof self) ? "$part" : static::PLACEHOLDER;
                if (preg_match(static::SQL_OPERATORS, $key) > 0) {
                    $parts[$key] = "$key $value";
                } else {
                    $parts[$key] = "$key = $value";
                }
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildComparators(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = "$part";
            } else {
                $parts[$key] = "$key = $part";
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildAliases(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key)) {
                $parts[$key] = "$part";
            } else {
                $parts[$key] = "$part AS $key";
            }
        }

        return $this->buildParts($parts, $format, $partSeparator);
    }

    protected function buildValuesList(array $parts): static
    {
        foreach ($parts as $key => $part) {
            if (is_numeric($key) || $part instanceof self) {
                $parts[$key] = "$part";
            } else {
                $parts[$key] = static::PLACEHOLDER;
            }
        }

        return $this->buildParts($parts, '(%s) ', ', ');
    }

    protected function buildOperation(string $operation): void
    {
        $command = strtoupper(preg_replace('/[A-Z0-9]+/', ' $0', $operation));
        if ($command == '_') {
            $this->query = rtrim($this->query).') ';
        } elseif ($command[0] == '_') {
            $this->query .= '('.trim($command, '_ ').' ';
        } elseif (substr($command, -1) == '_') {
            $this->query .= trim($command, '_ ').' (';
        } else {
            $this->query .= trim($command).' ';
        }
    }

    protected function buildFirstPart(array &$parts): void
    {
        $this->query .= array_shift($parts).' ';
    }

    protected function buildParts(array $parts, string $format = '%s ', string $partSeparator = ', '): static
    {
        if (!empty($parts)) {
            $this->query .= sprintf($format, implode($partSeparator, $parts));
        }

        return $this;
    }

    protected function normalizeParts(array $parts, bool $raw = false): array
    {
        $params = & $this->params;
        $newParts = [];

        array_walk_recursive($parts, function ($value, $key) use (&$newParts, &$params, &$raw) {
                if ($value instanceof Sql) {
                    $params = array_merge($params, $value->getParams());
                    if (0 !== stripos((string) $value, '(')) {
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
            }
        );

        return $newParts;
    }
}
