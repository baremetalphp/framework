<?php

namespace BareMetalPHP\Database;

/**
 * Base class for Data Mapper entities.
 */
abstract class Entity
{
    /**
     * Original attribute values as loaded from the DB.
     *
     * @var array<array,mixed> $original
     */
    protected array $original = [];

    /**
     * Attributers that have been modified since laad/flush.
     *
     * @var array>string,mixed>
     */
    protected array $dirty = [];

    /**
     * Mark this entity as having been loaded from a given row.
     *
     * @param array $row
     * @return void
     */
    public function hydrateFromRow(array $row): void
    {
        foreach ($row as $key => $value) {
            // Assign directly; subclasse can override for casting if needed.
            $this->{$key} = $value;
        }

        $this->original = $row;
        $this->dirty = [];
    }

    /**
     * Called by setters / property hooks to mark a field dirty.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    protected function trackDirty(string $field, mixed $value): void
    {
        $this->dirty[$field] = $value;
    }

    /**
     * Get all dirty attributes.
     *
     * @return array<string,mixed>
     */
    public function getDirty(): array
    {
        return $this->dirty;
    }

    /**
     * Get all original attributes as last loaded from the database.
     *
     * @return array<string,mixed>
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Mark the entity as clean (after a successful insert/update).
     *
     * @return void
     */
    public function markClean(): void
    {
        $data = $this->toArray();
        $this->original = $data;
        $this->dirty = [];
    }

    /**
     * Determine if the entity represents a new row.
     *
     * @return bool
     */
    public function isNew(): bool
    {
        $pk = static::primaryKey();
        return !isset($this->{$pk});
    }

    /**
     * Get the entity attributes as an array.
     *
     * Default behavior uses geT_object_vars() and strip internal properties.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $data = get_object_vars($this);

        unset($data['original'], $data['dirty']);

        return $data;
    }

    /**
     * Get the database table name for this entity.
     *
     * @return string
     */
    abstract public static function table(): string;

    /**
     * Get the primary key column for this entity.
     *
     * @return string
     */
    abstract public static function primaryKey(): string;

}