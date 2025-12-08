<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Schema\ColumnDefinition;
use BareMetalPHP\Database\Schema\ForeignKeyDefinition;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function testColumnDefinitionCanBeCreated(): void
    {
        $column = new ColumnDefinition('name', 'string');
        
        $this->assertSame('name', $column->name);
        $this->assertSame('string', $column->type);
    }

    public function testColumnDefinitionNullable(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->nullable();
        
        $this->assertTrue($column->nullable);
    }

    public function testColumnDefinitionDefault(): void
    {
        $column = new ColumnDefinition('status', 'string');
        $column->default('active');
        
        $this->assertSame('active', $column->default);
    }

    public function testColumnDefinitionPrimary(): void
    {
        $column = new ColumnDefinition('id', 'integer');
        $column->primary();
        
        $this->assertTrue($column->primary);
    }

    public function testColumnDefinitionAutoIncrement(): void
    {
        $column = new ColumnDefinition('id', 'integer');
        $column->autoIncrement();
        
        $this->assertTrue($column->autoIncrement);
    }

    public function testColumnDefinitionUnique(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->unique();
        
        $this->assertTrue($column->unique);
    }

    public function testColumnDefinitionLength(): void
    {
        $column = new ColumnDefinition('name', 'string');
        $column->length(255);
        
        $this->assertSame(255, $column->length);
    }

    public function testColumnDefinitionUnsigned(): void
    {
        $column = new ColumnDefinition('age', 'integer');
        $column->unsigned();
        
        $this->assertTrue($column->unsigned);
    }

    public function testColumnDefinitionPrecision(): void
    {
        $column = new ColumnDefinition('price', 'decimal');
        $column->precision(10, 2);
        
        $this->assertSame(10, $column->precision);
        $this->assertSame(2, $column->scale);
    }

    public function testColumnDefinitionAfter(): void
    {
        $column = new ColumnDefinition('middle_name', 'string');
        $column->after('first_name');
        
        $this->assertSame('first_name', $column->after);
    }

    public function testColumnDefinitionComment(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $column->comment('User email address');
        
        $this->assertSame('User email address', $column->comment);
    }

    public function testColumnDefinitionFluentInterface(): void
    {
        $column = new ColumnDefinition('email', 'string');
        $result = $column->nullable()->default('')->unique();
        
        $this->assertSame($column, $result);
        $this->assertTrue($column->nullable);
        $this->assertSame('', $column->default);
        $this->assertTrue($column->unique);
    }

    public function testForeignKeyDefinitionCanBeCreated(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        
        $this->assertSame('fk_user_posts', $fk->name);
        $this->assertSame(['user_id'], $fk->columns);
    }

    public function testForeignKeyDefinitionReferences(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->references('users', 'id');
        
        $this->assertSame('users', $fk->references);
        $this->assertSame(['id'], $fk->on);
    }

    public function testForeignKeyDefinitionReferencesWithArray(): void
    {
        $fk = new ForeignKeyDefinition('fk_composite', ['user_id', 'post_id']);
        $fk->references('user_posts', ['user_id', 'post_id']);
        
        $this->assertSame('user_posts', $fk->references);
        $this->assertSame(['user_id', 'post_id'], $fk->on);
    }

    public function testForeignKeyDefinitionOnDelete(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->onDelete('CASCADE');
        
        $this->assertSame('CASCADE', $fk->onDelete);
    }

    public function testForeignKeyDefinitionOnUpdate(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->onUpdate('CASCADE');
        
        $this->assertSame('CASCADE', $fk->onUpdate);
    }

    public function testForeignKeyDefinitionCascade(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->cascade();
        
        $this->assertSame('CASCADE', $fk->onDelete);
        $this->assertSame('CASCADE', $fk->onUpdate);
    }

    public function testForeignKeyDefinitionRestrict(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->restrict();
        
        $this->assertSame('RESTRICT', $fk->onDelete);
        $this->assertSame('RESTRICT', $fk->onUpdate);
    }

    public function testForeignKeyDefinitionSetNull(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $fk->setNull();
        
        $this->assertSame('SET NULL', $fk->onDelete);
    }

    public function testForeignKeyDefinitionFluentInterface(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_posts', ['user_id']);
        $result = $fk->references('users', 'id')->onDelete('CASCADE')->onUpdate('CASCADE');
        
        $this->assertSame($fk, $result);
        $this->assertSame('users', $fk->references);
        $this->assertSame('CASCADE', $fk->onDelete);
        $this->assertSame('CASCADE', $fk->onUpdate);
    }
}
