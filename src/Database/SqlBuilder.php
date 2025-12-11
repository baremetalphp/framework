<?php

declare(strict_types=1);

namespace BareMetalPHP\Database;

use BareMetalPHP\Database\Driver\DriverInterface;

/**
 * Helper class for building common SQL patterns
 */
class SqlBuilder
{
    /**
     * Build a WHERE clause from where conditions
     */
    public static function buildWhereClause(
        array $wheres,
        DriverInterface $driver,
        array &$bindings
    ): string {
        if (empty($wheres)) {
            return '';
        }

        $parts = [];

        foreach ($wheres as $index => [$boolean, $column, $operator, $value]) {
            $boolean  = strtoupper($boolean);
            $operator = strtoupper($operator);

            // First condition uses WHERE instead of AND/OR
            $prefix = $index === 0 ? 'WHERE' : $boolean;

            // RAW clauses are passed through untouched
            if ($operator === 'RAW') {
                $parts[] = $prefix . ' ' . $column;
                continue;
            }

            $quotedColumn = $driver->quoteIdentifier($column);

            // Handle IN / NOT IN explicitly (including empty arrays)
            if ($operator === 'IN' || $operator === 'NOT IN') {
                $values = (array) $value;

                if (empty($values)) {
                    // IN () => always false, NOT IN () => always true
                    $clause = $operator === 'IN' ? '1 = 0' : '1 = 1';
                    $parts[] = $prefix . ' ' . $clause;
                    continue;
                }

                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $clause = sprintf(
                    '%s %s (%s)',
                    $quotedColumn,
                    $operator,
                    $placeholders
                );

                foreach ($values as $v) {
                    $bindings[] = $v;
                }

                $parts[] = $prefix . ' ' . $clause;
                continue;
            }

            // Handle NULL values for normal comparison operators
            if ($value === null) {
                if (in_array($operator, ['=', 'IS'], true)) {
                    $clause = $quotedColumn . ' IS NULL';
                } elseif (in_array($operator, ['!=', '<>', 'IS NOT'], true)) {
                    $clause = $quotedColumn . ' IS NOT NULL';
                } else {
                    $clause = $quotedColumn . ' ' . $operator . ' NULL';
                }

                // No binding for NULL
                $parts[] = $prefix . ' ' . $clause;
                continue;
            }

            // Standard [column operator ?] clause
            $clause = $quotedColumn . ' ' . $operator . ' ?';
            $bindings[] = $value;

            $parts[] = $prefix . ' ' . $clause;
        }

        return ' ' . implode(' ', $parts);
    }

    /**
     * Build an ORDER BY clause from order conditions
     */
    public static function buildOrderByClause(array $orders, DriverInterface $driver): string
    {
        if (empty($orders)) {
            return '';
        }

        $orderParts = [];
        foreach ($orders as [$column, $dir]) {
            $quotedColumn = $driver->quoteIdentifier($column);
            $orderParts[] = $quotedColumn . ' ' . strtoupper($dir);
        }

        return ' ORDER BY ' . implode(', ', $orderParts);
    }
}

