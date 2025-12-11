<?php

declare(strict_types=1);

namespace Tests\Unit;

use BareMetalPHP\Database\Connection;
use BareMetalPHP\Database\Driver\SqliteDriver;
use BareMetalPHP\Database\Schema\Blueprint;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    protected bool $needsDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function createBlueprint(string $table = 'test_table'): Blueprint
    {
        $driver = new SqliteDriver();
        return new Blueprint($driver, $table);
    }

    public function testCanCreateBlueprint(): void
    {
        $blueprint = $this->createBlueprint('users');
        
        $this->assertSame('users', $blueprint->table);
        $this->assertEmpty($blueprint->columns);
    }

    public function testIdColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->id();
        
        $this->assertSame('id', $column->name);
        $this->assertTrue($column->primary);
        $this->assertTrue($column->autoIncrement);
    }

    public function testIdColumnWithCustomName(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->id('user_id');
        
        $this->assertSame('user_id', $column->name);
        $this->assertTrue($column->primary);
    }

    public function testBigIdColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->bigId();
        
        $this->assertSame('id', $column->name);
        $this->assertSame('bigInteger', $column->type);
        $this->assertTrue($column->primary);
    }

    public function testStringColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->string('name');
        
        $this->assertSame('name', $column->name);
        $this->assertSame('string', $column->type);
        $this->assertSame(255, $column->length);
    }

    public function testStringColumnWithCustomLength(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->string('name', 100);
        
        $this->assertSame(100, $column->length);
    }

    public function testTextColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->text('description');
        
        $this->assertSame('description', $column->name);
        $this->assertSame('text', $column->type);
    }

    public function testIntegerColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->integer('age');
        
        $this->assertSame('age', $column->name);
        $this->assertSame('integer', $column->type);
    }

    public function testBooleanColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->boolean('is_active');
        
        $this->assertSame('is_active', $column->name);
        $this->assertSame('boolean', $column->type);
    }

    public function testDecimalColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->decimal('price', 10, 2);
        
        $this->assertSame('price', $column->name);
        $this->assertSame('decimal', $column->type);
        $this->assertSame(10, $column->precision);
        $this->assertSame(2, $column->scale);
    }

    public function testDateTimeColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->dateTime('created_at');
        
        $this->assertSame('created_at', $column->name);
        $this->assertSame('dateTime', $column->type);
    }

    public function testTimestamps(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->timestamps();
        
        $this->assertTrue($blueprint->timestamps);
        $this->assertCount(2, $blueprint->columns);
        $this->assertSame('created_at', $blueprint->columns[0]->name);
        $this->assertSame('updated_at', $blueprint->columns[1]->name);
    }

    public function testPrimaryIndex(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->primary('id');
        
        $this->assertArrayHasKey('primary', $blueprint->indexes);
        $this->assertSame('primary', $blueprint->indexes['primary']['type']);
    }

    public function testUniqueIndex(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->unique('email');
        
        $this->assertNotEmpty($blueprint->indexes);
        $index = array_values($blueprint->indexes)[0];
        $this->assertSame('unique', $index['type']);
    }

    public function testRegularIndex(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->index('name');
        
        $this->assertNotEmpty($blueprint->indexes);
        $index = array_values($blueprint->indexes)[0];
        $this->assertSame('index', $index['type']);
    }

    public function testForeignKey(): void
    {
        $blueprint = $this->createBlueprint('posts');
        $fk = $blueprint->foreign('user_id')->references('users', 'id');
        
        $this->assertNotEmpty($blueprint->foreignKeys);
        $this->assertSame('user_id', $fk->columns[0]);
        $this->assertSame('users', $fk->references);
    }

    public function testIfNotExists(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->ifNotExists();
        
        $this->assertTrue($blueprint->ifNotExists);
    }

    public function testTemporary(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->temporary();
        
        $this->assertTrue($blueprint->temporary);
    }

    public function testToSqlCreatesTable(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->id();
        $blueprint->string('name');
        $blueprint->string('email')->unique();
        
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('[users]', $sql);
        $this->assertStringContainsString('[id]', $sql);
        $this->assertStringContainsString('[name]', $sql);
        $this->assertStringContainsString('[email]', $sql);
    }

    public function testToSqlWithIfNotExists(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->ifNotExists();
        $blueprint->id();
        
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('IF NOT EXISTS', $sql);
    }

    public function testToSqlWithTemporary(): void
    {
        $blueprint = $this->createBlueprint('temp_users');
        $blueprint->temporary();
        $blueprint->id();
        
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('CREATE TEMPORARY TABLE', $sql);
    }

    public function testDropColumn(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->dropColumn('email');
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertContains('email', $blueprint->dropColumns);
    }

    public function testDropMultipleColumns(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->dropColumn(['email', 'phone']);
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertContains('email', $blueprint->dropColumns);
        $this->assertContains('phone', $blueprint->dropColumns);
    }

    public function testRenameColumn(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->renameColumn('old_name', 'new_name');
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertSame('new_name', $blueprint->renameColumns['old_name']);
    }

    public function testDropIndex(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->dropIndex('email_index');
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertContains('email_index', $blueprint->dropIndexes);
    }

    public function testDropForeignKey(): void
    {
        $blueprint = $this->createBlueprint('posts');
        $blueprint->dropForeignKey('fk_user_id');
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertContains('fk_user_id', $blueprint->dropForeignKeys);
    }

    public function testRenameTable(): void
    {
        $blueprint = $this->createBlueprint('old_table');
        $blueprint->rename('new_table');
        
        $this->assertTrue($blueprint->isAlter);
        $this->assertSame('new_table', $blueprint->renameTable);
    }

    public function testToAlterSqlReturnsEmptyWhenNotAlter(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->id();
        
        $sql = $blueprint->toAlterSql();
        
        $this->assertEmpty($sql);
    }

    public function testToAlterSqlWithRenameTable(): void
    {
        $blueprint = $this->createBlueprint('old_table');
        $blueprint->rename('new_table');
        
        $sql = $blueprint->toAlterSql();
        
        $this->assertStringContainsString('ALTER TABLE', $sql);
        $this->assertStringContainsString('RENAME TO', $sql);
    }

    public function testToAlterSqlWithAddColumn(): void
    {
        $blueprint = $this->createBlueprint('users');
        $blueprint->isAlter = true;
        $blueprint->string('new_column');
        
        $sql = $blueprint->toAlterSql();
        
        $this->assertStringContainsString('ALTER TABLE', $sql);
        $this->assertStringContainsString('ADD COLUMN', $sql);
        $this->assertStringContainsString('[new_column]', $sql);
    }

    public function testJsonColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->json('metadata');
        
        $this->assertSame('metadata', $column->name);
        $this->assertSame('json', $column->type);
    }

    public function testUuidColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->uuid('uuid');
        
        $this->assertSame('uuid', $column->name);
        $this->assertSame('uuid', $column->type);
    }

    public function testIpAddressColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->ipAddress('ip');
        
        $this->assertSame('ip', $column->name);
        $this->assertSame('string', $column->type);
        $this->assertSame(45, $column->length);
    }

    public function testMacAddressColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->macAddress('mac');
        
        $this->assertSame('mac', $column->name);
        $this->assertSame('string', $column->type);
        $this->assertSame(17, $column->length);
    }

    public function testDateColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->date('birthday');
        
        $this->assertSame('birthday', $column->name);
        $this->assertSame('date', $column->type);
    }

    public function testTimeColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->time('start_time');
        
        $this->assertSame('start_time', $column->name);
        $this->assertSame('time', $column->type);
    }

    public function testTimestampColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->timestamp('created_at');
        
        $this->assertSame('created_at', $column->name);
        $this->assertSame('timestamp', $column->type);
    }

    public function testBinaryColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->binary('data');
        
        $this->assertSame('data', $column->name);
        $this->assertSame('binary', $column->type);
    }

    public function testBigIntegerColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->bigInteger('big_id');
        
        $this->assertSame('big_id', $column->name);
        $this->assertSame('bigInteger', $column->type);
    }

    public function testSmallIntegerColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->smallInteger('status');
        
        $this->assertSame('status', $column->name);
        $this->assertSame('smallInteger', $column->type);
    }

    public function testTinyIntegerColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->tinyInteger('flag');
        
        $this->assertSame('flag', $column->name);
        $this->assertSame('tinyInteger', $column->type);
    }

    public function testFloatColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->float('rate');
        
        $this->assertSame('rate', $column->name);
        $this->assertSame('float', $column->type);
    }

    public function testDoubleColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->double('precision');
        
        $this->assertSame('precision', $column->name);
        $this->assertSame('double', $column->type);
    }

    public function testMediumTextColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->mediumText('content');
        
        $this->assertSame('content', $column->name);
        $this->assertSame('mediumText', $column->type);
    }

    public function testLongTextColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->longText('article');
        
        $this->assertSame('article', $column->name);
        $this->assertSame('longText', $column->type);
    }

    public function testYearColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->year('year');
        
        $this->assertSame('year', $column->name);
        $this->assertSame('year', $column->type);
    }

    public function testJsonbColumn(): void
    {
        $blueprint = $this->createBlueprint();
        $column = $blueprint->jsonb('data');
        
        $this->assertSame('data', $column->name);
        $this->assertSame('jsonb', $column->type);
    }

    public function testCompositePrimaryKey(): void
    {
        $blueprint = $this->createBlueprint('user_roles');
        $blueprint->primary(['user_id', 'role_id']);
        
        $this->assertArrayHasKey('primary', $blueprint->indexes);
        $this->assertSame(['user_id', 'role_id'], $blueprint->indexes['primary']['columns']);
    }

    public function testCompositeUniqueIndex(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->unique(['email', 'domain']);
        
        $index = array_values($blueprint->indexes)[0];
        $this->assertSame(['email', 'domain'], $index['columns']);
    }

    public function testCompositeIndex(): void
    {
        $blueprint = $this->createBlueprint();
        $blueprint->index(['first_name', 'last_name']);
        
        $index = array_values($blueprint->indexes)[0];
        $this->assertSame(['first_name', 'last_name'], $index['columns']);
    }

    public function testCompositeForeignKey(): void
    {
        $blueprint = $this->createBlueprint('order_items');
        $fk = $blueprint->foreign(['order_id', 'product_id'])
            ->references('orders_products', ['order_id', 'product_id']);
        
        $this->assertSame(['order_id', 'product_id'], $fk->columns);
        $this->assertSame(['order_id', 'product_id'], $fk->on);
    }
}

