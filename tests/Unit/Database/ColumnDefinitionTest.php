<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Schema\ColumnDefinition;
use Tests\TestCase;

class ColumnDefinitionTest extends TestCase
{
    public function testCanCreateColumnDefinition(): void
    {
        $column = new ColumnDefinition('name', 'VARCHAR');
        
        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertEquals('name', $column->name);
        $this->assertEquals('VARCHAR', $column->type);
    }

    public function testNullable(): void
    {
        $column = new ColumnDefinition('email', 'VARCHAR');
        
        $this->assertFalse($column->nullable);
        
        $result = $column->nullable();
        $this->assertSame($column, $result);
        $this->assertTrue($column->nullable);
        
        $column->nullable(false);
        $this->assertFalse($column->nullable);
    }

    public function testDefault(): void
    {
        $column = new ColumnDefinition('status', 'VARCHAR');
        
        $result = $column->default('active');
        $this->assertSame($column, $result);
        $this->assertEquals('active', $column->default);
    }

    public function testPrimary(): void
    {
        $column = new ColumnDefinition('id', 'INTEGER');
        
        $this->assertFalse($column->primary);
        
        $result = $column->primary();
        $this->assertSame($column, $result);
        $this->assertTrue($column->primary);
        
        $column->primary(false);
        $this->assertFalse($column->primary);
    }

    public function testAutoIncrement(): void
    {
        $column = new ColumnDefinition('id', 'INTEGER');
        
        $this->assertFalse($column->autoIncrement);
        
        $result = $column->autoIncrement();
        $this->assertSame($column, $result);
        $this->assertTrue($column->autoIncrement);
        
        $column->autoIncrement(false);
        $this->assertFalse($column->autoIncrement);
    }

    public function testUnique(): void
    {
        $column = new ColumnDefinition('email', 'VARCHAR');
        
        $this->assertFalse($column->unique);
        
        $result = $column->unique();
        $this->assertSame($column, $result);
        $this->assertTrue($column->unique);
        
        $column->unique(false);
        $this->assertFalse($column->unique);
    }

    public function testLength(): void
    {
        $column = new ColumnDefinition('name', 'VARCHAR');
        
        $this->assertNull($column->length);
        
        $result = $column->length(255);
        $this->assertSame($column, $result);
        $this->assertEquals(255, $column->length);
    }

    public function testUnsigned(): void
    {
        $column = new ColumnDefinition('age', 'INTEGER');
        
        $this->assertFalse($column->unsigned);
        
        $result = $column->unsigned();
        $this->assertSame($column, $result);
        $this->assertTrue($column->unsigned);
        
        $column->unsigned(false);
        $this->assertFalse($column->unsigned);
    }

    public function testPrecision(): void
    {
        $column = new ColumnDefinition('price', 'DECIMAL');
        
        $this->assertNull($column->precision);
        $this->assertNull($column->scale);
        
        $result = $column->precision(10, 2);
        $this->assertSame($column, $result);
        $this->assertEquals(10, $column->precision);
        $this->assertEquals(2, $column->scale);
    }

    public function testPrecisionWithDefaultScale(): void
    {
        $column = new ColumnDefinition('price', 'DECIMAL');
        
        $column->precision(10);
        $this->assertEquals(10, $column->precision);
        $this->assertEquals(0, $column->scale);
    }

    public function testAfter(): void
    {
        $column = new ColumnDefinition('middle_name', 'VARCHAR');
        
        $this->assertNull($column->after);
        
        $result = $column->after('first_name');
        $this->assertSame($column, $result);
        $this->assertEquals('first_name', $column->after);
    }

    public function testComment(): void
    {
        $column = new ColumnDefinition('status', 'VARCHAR');
        
        $this->assertNull($column->comment);
        
        $result = $column->comment('User status');
        $this->assertSame($column, $result);
        $this->assertEquals('User status', $column->comment);
    }

    public function testFluentInterface(): void
    {
        $column = new ColumnDefinition('email', 'VARCHAR')
            ->length(255)
            ->nullable()
            ->unique()
            ->default('')
            ->comment('User email address');
        
        $this->assertEquals(255, $column->length);
        $this->assertTrue($column->nullable);
        $this->assertTrue($column->unique);
        $this->assertEquals('', $column->default);
        $this->assertEquals('User email address', $column->comment);
    }
}

