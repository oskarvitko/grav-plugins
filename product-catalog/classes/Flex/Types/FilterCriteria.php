<?php

declare(strict_types=1);

namespace Grav\Plugin\ProductCatalog\Flex\Types;

use InvalidArgumentException;

class FilterCriteria
{
    public function buildPredicate(array $filters): callable
    {
        return $this->buildExpression($filters);
    }

    private function buildExpression(array $filters): callable
    {
        $predicates = [];

        foreach ($filters as $field => $value) {
            if ($field === 'and') {
                $predicates[] = $this->buildLogicalGroup('and', $value);
                continue;
            }

            if ($field === 'or') {
                $predicates[] = $this->buildLogicalGroup('or', $value);
                continue;
            }

            if ($field === 'not') {
                $inner = $this->buildLogicalGroup('and', $value);

                $predicates[] = static fn($item): bool => !$inner($item);
                continue;
            }

            $predicates[] = $this->buildFieldPredicate((string) $field, $value);
        }

        return static function ($item) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (!$predicate($item)) {
                    return false;
                }
            }

            return true;
        };
    }

    private function buildLogicalGroup(string $type, mixed $items): callable
    {
        if (!is_array($items) || $items === []) {
            throw new InvalidArgumentException(sprintf('Logical group "%s" must be a non-empty array.', $type));
        }

        $predicates = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Logical group items must be arrays.');
            }

            $predicates[] = $this->buildExpression($item);
        }

        if ($type === 'or') {
            return static function ($item) use ($predicates): bool {
                foreach ($predicates as $predicate) {
                    if ($predicate($item)) {
                        return true;
                    }
                }

                return false;
            };
        }

        return static function ($item) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (!$predicate($item)) {
                    return false;
                }
            }

            return true;
        };
    }

    private function buildFieldPredicate(string $field, mixed $value): callable
    {
        if (!is_array($value) || $this->isList($value)) {
            return fn($item): bool => $this->readFieldMeta($item, $field)['value'] === $value;
        }

        $predicates = [];

        foreach ($value as $operator => $operatorValue) {
            $predicates[] = $this->buildOperatorPredicate($field, (string) $operator, $operatorValue);
        }

        return static function ($item) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (!$predicate($item)) {
                    return false;
                }
            }

            return true;
        };
    }

    private function buildOperatorPredicate(string $field, string $operator, mixed $operatorValue): callable
    {
        return match ($operator) {
            'eq' => fn($item): bool => $this->readFieldMeta($item, $field)['value'] === $operatorValue,

            'neq' => fn($item): bool => $this->readFieldMeta($item, $field)['value'] !== $operatorValue,

            'lt' => fn($item): bool => $this->compare($this->readFieldMeta($item, $field)['value'], $operatorValue, '<'),

            'lte' => fn($item): bool => $this->compare($this->readFieldMeta($item, $field)['value'], $operatorValue, '<='),

            'gt' => fn($item): bool => $this->compare($this->readFieldMeta($item, $field)['value'], $operatorValue, '>'),

            'gte' => fn($item): bool => $this->compare($this->readFieldMeta($item, $field)['value'], $operatorValue, '>='),

            'in' => fn($item): bool => in_array(
                $this->readFieldMeta($item, $field)['value'],
                (array) $operatorValue,
                true
            ),

            'notIn', 'nin' => fn($item): bool => !in_array(
                $this->readFieldMeta($item, $field)['value'],
                (array) $operatorValue,
                true
            ),

            'contains' => fn($item): bool => str_contains(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ),

            'icontains' => fn($item): bool => mb_stripos(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ) !== false,

            'startsWith' => fn($item): bool => str_starts_with(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ),

            'istartsWith' => fn($item): bool => mb_stripos(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ) === 0,

            'endsWith' => fn($item): bool => str_ends_with(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ),

            'iendsWith' => fn($item): bool => $this->mbEndsWithInsensitive(
                (string) ($this->readFieldMeta($item, $field)['value'] ?? ''),
                (string) $operatorValue
            ),

            'isNull' => fn($item): bool => $this->readFieldMeta($item, $field)['exists']
            && $this->readFieldMeta($item, $field)['value'] === null,

            'notNull' => fn($item): bool => $this->readFieldMeta($item, $field)['value'] !== null,

            'isMissing' => fn($item): bool => !$this->readFieldMeta($item, $field)['exists'],

            'isMissingOrNull' => fn($item): bool => !$this->readFieldMeta($item, $field)['exists']
            || $this->readFieldMeta($item, $field)['value'] === null,

            'memberOf' => fn($item): bool => $this->memberOf(
                $this->readFieldMeta($item, $field)['value'],
                $operatorValue
            ),

            default => throw new InvalidArgumentException(
                sprintf('Unsupported filter operator "%s" for field "%s".', $operator, $field)
            ),
        };
    }

    private function readFieldMeta(mixed $item, string $field): array
    {
        if (is_array($item)) {
            $exists = array_key_exists($field, $item);

            return [
                'exists' => $exists,
                'value' => $exists ? $item[$field] : null,
            ];
        }

        if (is_object($item)) {
            if (method_exists($item, 'hasProperty') && method_exists($item, 'getProperty')) {
                $exists = $item->hasProperty($field);

                return [
                    'exists' => $exists,
                    'value' => $exists ? $item->getProperty($field) : null,
                ];
            }

            $getter = 'get' . ucfirst($field);
            if (method_exists($item, $getter)) {
                return [
                    'exists' => true,
                    'value' => $item->{$getter}(),
                ];
            }

            if (property_exists($item, $field)) {
                return [
                    'exists' => true,
                    'value' => $item->{$field},
                ];
            }
        }

        return [
            'exists' => false,
            'value' => null,
        ];
    }

    private function compare(mixed $actual, mixed $expected, string $operator): bool
    {
        if ($actual === null) {
            return false;
        }

        return match ($operator) {
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            default => false,
        };
    }

    private function memberOf(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected)) {
            return in_array($actual, $expected, true);
        }

        if ($expected instanceof \Traversable) {
            foreach ($expected as $item) {
                if ($item === $actual) {
                    return true;
                }
            }
        }

        return false;
    }

    private function mbEndsWithInsensitive(string $haystack, string $needle): bool
    {
        $haystack = mb_strtolower($haystack);
        $needle = mb_strtolower($needle);

        if ($needle === '') {
            return true;
        }

        return mb_substr($haystack, -mb_strlen($needle)) === $needle;
    }

    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}