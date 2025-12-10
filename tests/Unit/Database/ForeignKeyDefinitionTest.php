<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use BareMetalPHP\Database\Schema\ForeignKeyDefinition;
use Tests\TestCase;

class ForeignKeyDefinitionTest extends TestCase
{
    public function testCanCreateForeignKeyDefinition(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $this->assertInstanceOf(ForeignKeyDefinition::class, $fk);
        $this->assertEquals('fk_user_id', $fk->name);
        $this->assertEquals(['user_id'], $fk->columns);
    }

    public function testReferencesWithStringColumn(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->references('users', 'id');
        
        $this->assertSame($fk, $result);
        $this->assertEquals('users', $fk->references);
        $this->assertEquals(['id'], $fk->on);
    }

    public function testReferencesWithArrayColumns(): void
    {
        $fk = new ForeignKeyDefinition('fk_composite', ['user_id', 'role_id']);
        
        $fk->references('user_roles', ['id', 'role_id']);
        
        $this->assertEquals('user_roles', $fk->references);
        $this->assertEquals(['id', 'role_id'], $fk->on);
    }

    public function testOnDelete(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->onDelete('CASCADE');
        
        $this->assertSame($fk, $result);
        $this->assertEquals('CASCADE', $fk->onDelete);
    }

    public function testOnUpdate(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->onUpdate('RESTRICT');
        
        $this->assertSame($fk, $result);
        $this->assertEquals('RESTRICT', $fk->onUpdate);
    }

    public function testCascade(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->cascade();
        
        $this->assertSame($fk, $result);
        $this->assertEquals('CASCADE', $fk->onDelete);
        $this->assertEquals('CASCADE', $fk->onUpdate);
    }

    public function testRestrict(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->restrict();
        
        $this->assertSame($fk, $result);
        $this->assertEquals('RESTRICT', $fk->onDelete);
        $this->assertEquals('RESTRICT', $fk->onUpdate);
    }

    public function testSetNull(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id']);
        
        $result = $fk->setNull();
        
        $this->assertSame($fk, $result);
        $this->assertEquals('SET NULL', $fk->onDelete);
        // onUpdate should remain null
        $this->assertNull($fk->onUpdate);
    }

    public function testFluentInterface(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id'])
            ->references('users', 'id')
            ->onDelete('CASCADE')
            ->onUpdate('RESTRICT');
        
        $this->assertEquals('users', $fk->references);
        $this->assertEquals(['id'], $fk->on);
        $this->assertEquals('CASCADE', $fk->onDelete);
        $this->assertEquals('RESTRICT', $fk->onUpdate);
    }

    public function testCascadeOverridesPreviousActions(): void
    {
        $fk = new ForeignKeyDefinition('fk_user_id', ['user_id'])
            ->onDelete('RESTRICT')
            ->onUpdate('SET NULL')
            ->cascade();
        
        $this->assertEquals('CASCADE', $fk->onDelete);
        $this->assertEquals('CASCADE', $fk->onUpdate);
    }
}

