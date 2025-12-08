<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Driver\SqliteDriver;
use BareMetalPHP\Database\SqlBuilder;
use Tests\TestCase;

class SqlBuilderTest extends TestCase
{
    protected SqliteDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new SqliteDriver();
    }

    public function testBuildWhereClauseWithSimpleCondition(): void
    {
        $wheres = [
            ['AND', 'name', '=', 'John']
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('WHERE', $clause);
        $this->assertStringContainsString('"name"', $clause);
        $this->assertStringContainsString('?', $clause);
        $this->assertCount(1, $bindings);
        $this->assertSame('John', $bindings[0]);
    }

    public function testBuildWhereClauseWithMultipleConditions(): void
    {
        $wheres = [
            ['AND', 'name', '=', 'John'],
            ['AND', 'age', '>', 25],
            ['OR', 'status', '=', 'active']
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('WHERE', $clause);
        $this->assertStringContainsString('AND', $clause);
        $this->assertStringContainsString('OR', $clause);
        $this->assertCount(3, $bindings);
    }

    public function testBuildWhereClauseWithNullValue(): void
    {
        $wheres = [
            ['AND', 'deleted_at', '=', null]
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('IS NULL', $clause);
        $this->assertCount(0, $bindings);
    }

    public function testBuildWhereClauseWithNotNullValue(): void
    {
        $wheres = [
            ['AND', 'deleted_at', '!=', null]
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('IS NOT NULL', $clause);
        $this->assertCount(0, $bindings);
    }

    public function testBuildWhereClauseWithInOperator(): void
    {
        $wheres = [
            ['AND', 'id', 'IN', [1, 2, 3]]
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('IN', $clause);
        $this->assertStringContainsString('(?,?,?)', $clause);
        $this->assertCount(3, $bindings);
        $this->assertSame([1, 2, 3], $bindings);
    }

    public function testBuildWhereClauseWithNotInOperator(): void
    {
        $wheres = [
            ['AND', 'id', 'NOT IN', [1, 2, 3]]
        ];
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause($wheres, $this->driver, $bindings);
        
        $this->assertStringContainsString('NOT IN', $clause);
        $this->assertStringContainsString('(?,?,?)', $clause);
        $this->assertCount(3, $bindings);
    }

    public function testBuildWhereClauseReturnsEmptyStringWhenNoConditions(): void
    {
        $bindings = [];
        
        $clause = SqlBuilder::buildWhereClause([], $this->driver, $bindings);
        
        $this->assertSame('', $clause);
        $this->assertCount(0, $bindings);
    }

    public function testBuildOrderByClauseWithSingleColumn(): void
    {
        $orders = [
            ['name', 'ASC']
        ];
        
        $clause = SqlBuilder::buildOrderByClause($orders, $this->driver);
        
        $this->assertStringContainsString('ORDER BY', $clause);
        $this->assertStringContainsString('"name"', $clause);
        $this->assertStringContainsString('ASC', $clause);
    }

    public function testBuildOrderByClauseWithMultipleColumns(): void
    {
        $orders = [
            ['name', 'ASC'],
            ['created_at', 'DESC']
        ];
        
        $clause = SqlBuilder::buildOrderByClause($orders, $this->driver);
        
        $this->assertStringContainsString('ORDER BY', $clause);
        $this->assertStringContainsString('"name"', $clause);
        $this->assertStringContainsString('"created_at"', $clause);
        $this->assertStringContainsString('ASC', $clause);
        $this->assertStringContainsString('DESC', $clause);
    }

    public function testBuildOrderByClauseReturnsEmptyStringWhenNoOrders(): void
    {
        $clause = SqlBuilder::buildOrderByClause([], $this->driver);
        
        $this->assertSame('', $clause);
    }
}
