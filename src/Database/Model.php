<?php

declare(strict_types=1);

namespace BareMetalPHP\Database;

use BareMetalPHP\Application;
use BareMetalPHP\Database\ConnectionManager;
use PDO;
use ArrayAccess;
use BareMetalPHP\Support\Collection;
use BareMetalPHP\Database\Relations\HasMany;
use BareMetalPHP\Database\Relations\BelongsTo;
use BareMetalPHP\Database\Relations\HasOne;
use BareMetalPHP\Database\Relations\MorphMany;
use BareMetalPHP\Database\Relations\MorphOne;
use BareMetalPHP\Database\Relations\MorphTo;
use BareMetalPHP\Database\Relations\Relation as BaseRelation;
abstract class Model implements ArrayAccess
{
    // Constants for common column names
    protected const PRIMARY_KEY = 'id';
    protected const CREATED_AT = 'created_at';
    protected const UPDATED_AT = 'updated_at';

    // Default connection name
    protected const DEFAULT_CONNECTION = 'default';

    protected static ?PDO $pdo = null;
    protected static ?Connection $connection = null;
    protected static string $connectionName = self::DEFAULT_CONNECTION;

    protected static string $table;

    /**
     * Mass assignable attributes.
     *
     * @var string[] $fillable
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass-assignable.
     * By default, everything is guarded until explicitly allowed via $fillable.
     *
     * @var string[] $guarded
     */
    protected array $guarded = ['*'];
    protected bool $timestamps = true;

    protected array $attributes = [];

    /**
     * Loaded relationship values keyed by relation name.
     * 
     * @var array<string, mixed>
     */
    protected array $relations = [];

    /**
     * Default relations to eager load for this model.
     * 
     * Example in a child model:
     *     protected array $with = ['posts', 'profile'];
     * @var array<int, string>
     */
    protected array $with = [];

    /**
     * Whether this model instance should lazily load relationships
     * when accessed as properties ($user->posts).
     * @var bool
     */
    protected bool $lazyLoading = true;

    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        if (isset($attributes['id'])) {
            $this->exists = true;
        }
    }

    /**
     * Fill the model with an array of attributes, applying mass-assignment rules.
     *
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (! $this->isFillable($key)) {
                continue;
            }

            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Determine if the given key is mass assignable.
     * 
     * @param string $key
     * @return bool
     */
    protected function isFillable(string $key): bool
    {
        // Explicitly fillable
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        if (in_array('*', $this->guarded, true)) {
            return false;
        }

        return ! in_array($key, $this->guarded, true);
    }

    // called once during bootstrap
    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
        static::$pdo = $connection->pdo();
    }

    /**
     * Set the connection name for this model
     */
    public static function setConnectionName(string $name): void
    {
        static::$connectionName = $name;
    }

    /**
     * Get the connection for this model
     */
    public static function getConnection(): Connection
    {
        if (static::$connection !== null) {
            return static::$connection;
        }

        // Try to get from ConnectionManager if available
        $app = Application::getInstance();
        if ($app) {
            try {
                $manager = $app->make(ConnectionManager::class);
                return $manager->connection(static::$connectionName);
            } catch (\Exception $e) {
                // Fallback to default connection
            }
        }

        // Final fallback: if we have a PDO, create a connection wrapper
        if (static::$pdo !== null) {
            // Use reflection to create connection without calling constructor
            $reflection = new \ReflectionClass(Connection::class);
            $connection = $reflection->newInstanceWithoutConstructor();
            $pdoProperty = $reflection->getProperty('pdo');
            $pdoProperty->setAccessible(true);
            $pdoProperty->setValue($connection, static::$pdo);
            
            // Set SQLite driver as default fallback
            $driver = new \BareMetalPHP\Database\Driver\SqliteDriver();
            $driverProperty = $reflection->getProperty('driver');
            $driverProperty->setAccessible(true);
            $driverProperty->setValue($connection, $driver);
            
            return $connection;
        }

        throw new \RuntimeException('No database connection available for ' . static::class);
    }
    

    // query builder entry
    public static function query(): Builder
    {
        $connection = static::getConnection();
        $pdo = $connection->pdo();
        
        $builder = new Builder($pdo, static::$table, static::class, $connection);
        
        // Apply global scopes if any
        static::applyGlobalScopes($builder);

        // Apply model's default eager-loaded relations (protected $with = [...] )
        $instance = new static();
        if (! empty($instance->with)) {
            $builder->with($instance->with);
        }
        
        return $builder;
    }

    /**
     * Eager load one or more relationships on this model instance.
     * 
     * Example:
     *     $user->load('posts', 'profile')
     * @param string|array[] $relations
     * @return Model
     */
    public function load(string|array ...$relations): static
    {
        if (count($relations) === 1 && is_array($relations)) {
            $relations = $relations[0];
        }

        foreach ($relations as $relation) {
            // Just trigger the lazy loader once: __get will cache it into $relations
            $this->{$relation};
        }

        return $this;
    }

    /**
     * Begin a query with the given relations eager-loaded
     * 
     * Example:
     *     User::with('posts', 'profile')->where('active', true)->get();
     * @param string|array[] $relations
     * @return Builder
     */
    public static function with(string|array ...$relations): Builder
    {
        // Allow both with('posts', 'profile') and with(['posts', 'profile])
        if (count($relations) === 1 && is_array($relations)) {
            $relations = $relations[0];
        }

        $builder = static::query();

        return $builder->with($relations);
    }

    /**
     *  Eager load relationships on a collection of models.
     * 
     * This is a simple implementation: it loops models and triggers 
     * the relationship once on each. It gives you "eager semantics" (no
     * extra queries after) even though it doesn't yet optimize N+1s.
     * 
     * @todo: implement n+1optimization
     * @param Collection $models
     * @param array $relations
     * @return void
     */
    public static function eagerLoadCollection(Collection $models, array $relations): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $first = $models->first();

        if (! $first instanceof static) {
            return;
        }

        $items = $models->all();

        foreach ($relations as $relationName) {
            if (!is_string($relationName) || !method_exists($first, $relationName)) {
                continue;
            }

            $relation = $first->getRelationObject($relationName);
            if ($relation === null) {
                continue;
            }

            // hasMany, HasOne, BelongsTo, MorphMany, etc.
            if ($relation instanceof BaseRelation) {
                $keys = $relation->addEagerConstraints($items);
                $results = $relation->getEager($keys);
                $relation->match($items, $results, $relationName);
            }

            // Fallback for other relation types (e.g. BelongsToMany): naive
            else {
                foreach ($items as $model) {
                    if ($model instanceof static) {
                        $model->{$relationName}; // triggers lazy load once
                    }
                }
            }
        }
    }

    /**
     * Determine if a given relationship is already loaded.
     * 
     * @param string $relation
     * @return bool
     */
    public function isRelationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Get a loaded relationship value.
     * @param string $relation
     * @return mixed|null
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;
        return $this;
    }
    /**
     * Get all loaded relations.
     * 
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }
    /**
     * Apply global scopes to the query builder
     */
    protected static function applyGlobalScopes(Builder $builder): void
    {
        // Override in child classes to add global scopes
        // Example:
        // $builder->where('active', '=', 1);
    }

    // basic query shortcuts

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function find(int|string $id, string $column = 'id')
    {
        /** @var static|null $result */
        $result = static::query()
        ->where($column,'=', $id)
        ->first();

        return $result;
    }

    /**
     * Find a model by ID or throw an exception
     */
    public static function findOrFail(int|string $id, string $column = 'id'): static
    {
        $model = static::find($id, $column);
        
        if (!$model) {
            throw new \RuntimeException("Model not found: " . static::class . " with {$column} = {$id}");
        }
        
        return $model;
    }

    public static function where(string $column, string $operator,  mixed $value = null): Collection
    {
        return static::query()->where($column, $operator, $value)->get();
    }

    /**
     * Create a new model instance and save it
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Update a model by ID (static helper)
     */
    public static function updateById(int|string $id, array $attributes): bool
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }
        return $model->update($attributes);
    }

    /**
     * Find or create a model
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $model = static::query()
            ->where(key($attributes), '=', reset($attributes))
            ->first();

        if ($model) {
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    /**
     * Find or create a model, then update it
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrCreate($attributes, $values);
        
        if (!empty($values)) {
            $model->fill($values);
            $model->save();
        }

        return $model;
    }

    /**
     * Get the first model matching the attributes or create it
     */
    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $model = static::query()
            ->where(key($attributes), '=', reset($attributes))
            ->first();

        if ($model) {
            return $model;
        }

        return new static(array_merge($attributes, $values));
    }



    public function save(): bool 
    {
        // naive: if "id" exists, update, else insert
        if ($this->exists || isset($this->attributes['id'])) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Fill the model with an array of attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Update the model with an array of attributes
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    /**
     * Refresh the model from the database
     */
    public function refresh(): static
    {
        if (!isset($this->attributes['id'])) {
            return $this;
        }

        $fresh = static::find($this->attributes['id']);
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->relations = [];
        }

        return $this;
    }

    /**
     * Get a fresh instance of the model from the database
     */
    public function fresh(): ?static
    {
        if (!isset($this->attributes['id'])) {
            return null;
        }

        return static::find($this->attributes['id']);
    }

    /**
     * Get an attribute value
     */
    public function getAttribute(string $key): mixed
    {
        // Accessor: getFullNameAttribute()
        if ($this->hasGetMutator($key)) {
            $method = $this->getGetMutatorName($key);
            return $this->{$method}();
        }
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if an attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Check if the model exists in the database
     */
    public function exists(): bool
    {
        return $this->exists || isset($this->attributes['id']);
    }

    /**
     * Get connection components for database operations
     * Returns [Connection, Driver, PDO] tuple
     */
    protected function getConnectionComponents(): array
    {
        $connection = static::getConnection();
        return [$connection, $connection->getDriver(), $connection->pdo()];
    }

    /**
     * Prepare attributes using driver-specific value preparation
     */
    protected function prepareAttributes(array $attributes, \BareMetalPHP\Database\Driver\DriverInterface $driver): array
    {
        $prepared = [];
        foreach ($attributes as $key => $value) {
            $prepared[$key] = $driver->prepareValue($value);
        }
        return $prepared;
    }

    /**
     * Quote multiple identifiers at once
     */
    protected function quoteIdentifiers(array $identifiers, \BareMetalPHP\Database\Driver\DriverInterface $driver): array
    {
        return array_map(fn ($id) => $driver->quoteIdentifier($id), $identifiers);
    }

    /**
     * Update timestamps if enabled
     */
    protected function updateTimestamps(bool $create = false): void
    {
        if (!$this->timestamps) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        
        if ($create) {
            $this->attributes[self::CREATED_AT] = $this->attributes[self::CREATED_AT] ?? $now;
        }
        
        $this->attributes[self::UPDATED_AT] = $now;
    }

    protected function performInsert(): bool
    {
        $this->updateTimestamps(true);

        [$connection, $driver, $pdo] = $this->getConnectionComponents();
        $preparedAttributes = $this->prepareAttributes($this->attributes, $driver);

        $columns = array_keys($preparedAttributes);
        $quotedColumns = $this->quoteIdentifiers($columns, $driver);
        $placeholders = array_map(fn ($c) => ':' . $c, $columns);
        $quotedTable = $driver->quoteIdentifier(static::$table);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quotedTable,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($preparedAttributes);

        if ($ok) {
            $this->attributes['id'] = (int) $pdo->lastInsertId();
            $this->exists = true;
        }

        return $ok;
    }

    protected function performUpdate(): bool
    {
        $this->updateTimestamps();
        
        [$connection, $driver, $pdo] = $this->getConnectionComponents();
        $preparedAttributes = $this->prepareAttributes($this->attributes, $driver);

        $assignments = [];
        foreach (array_keys($preparedAttributes) as $column) {
            if ($column === self::PRIMARY_KEY) {
                continue;
            }
            $quotedColumn = $driver->quoteIdentifier($column);
            $assignments[] = $quotedColumn . ' = :' . $column;
        }

        $quotedTable = $driver->quoteIdentifier(static::$table);
        $quotedIdColumn = $driver->quoteIdentifier('id');

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $quotedTable,
            implode(', ', $assignments),
            $quotedIdColumn
        );

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($preparedAttributes);
    }

    public function delete(): bool
    {
        if (!isset($this->attributes[self::PRIMARY_KEY])) {
            return false;
        }

        [$connection, $driver, $pdo] = $this->getConnectionComponents();

        $quotedTable = $driver->quoteIdentifier(static::$table);
        $quotedIdColumn = $driver->quoteIdentifier(self::PRIMARY_KEY);
        $sql = 'DELETE FROM ' . $quotedTable . ' WHERE ' . $quotedIdColumn . ' = :id';
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute(['id' => $this->attributes[self::PRIMARY_KEY]]);
    }
    
    // relationships

    /**
     * Define a one-to-many relationship
     */
    /**
     * Internal flag to track if we're getting relation object for eager loading
     */
    protected static bool $gettingRelationObject = false;

    /**
     * Get the relation object without executing the query (for eager loading)
     */
    protected function getRelationObject(string $relationName): ?BaseRelation
    {
        if (!method_exists($this, $relationName)) {
            return null;
        }

        static::$gettingRelationObject = true;
        $relation = $this->{$relationName}();
        static::$gettingRelationObject = false;

        return $relation instanceof BaseRelation ? $relation : null;
    }

    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): mixed
    {
        $relation = new HasMany($this, $related, $foreignKey, $localKey);
        
        // If we're getting the relation object for eager loading, return it directly
        // Otherwise, return the results (for direct method calls)
        if (static::$gettingRelationObject) {
            return $relation;
        }
        
        return $relation->getResults();
    }

    /**
     * Define a one-to-one relationship
     * 
     * We can reuse HasMany and just take the first result when lazy loading via __get()
     */
    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): mixed
    {
        $relation = new HasOne($this, $related, $foreignKey, $localKey);
        
        // If we're getting the relation object for eager loading, return it directly
        // Otherwise, return the results (for direct method calls)
        if (static::$gettingRelationObject) {
            return $relation;
        }
        
        return $relation->getResults();
    }

    /**
     * Define an inverse one-to-one or many-to-one relationship
     */
    protected function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): mixed
    {
        $relation = new BelongsTo($this, $related, $foreignKey, $ownerKey);
        
        // If we're getting the relation object for eager loading, return it directly
        // Otherwise, return the results (for direct method calls)
        if (static::$gettingRelationObject) {
            return $relation;
        }
        
        return $relation->getResults();
    }

    /**
     * Define a many-to-many relationship
     * 
     * @param string $related The related model class
     * @param string|null $pivotTable The pivot table name (auto-generated if null)
     * @param string|null $foreignPivotKey Foreign key for this model in pivot table (auto-generated if null)
     * @param string|null $relatedPivotKey Foreign key for related model in pivot table (auto-generated if null)
     * @param string $parentKey Local key on this model
     * @param string $relatedKey Local key on related model
     * 
     * @return \BareMetalPHP\Database\Relations\BelongsToMany
     */
    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        string $parentKey = 'id',
        string $relatedKey = 'id'
    ): \BareMetalPHP\Database\Relations\BelongsToMany {
        // Auto-generate pivot table name if not provided (Laravel convention: alphabetically sorted model names)
        if ($pivotTable === null) {
            $tables = [static::$table, (new $related)::$table];
            sort($tables);
            $pivotTable = implode('_', $tables);
        }

        // Auto-generate foreign key names if not provided (Laravel convention: snake_case model name + _id)
        if ($foreignPivotKey === null) {
            $foreignPivotKey = $this->getModelForeignKeyName();
        }

        if ($relatedPivotKey === null) {
            $relatedModel = new $related();
            $relatedPivotKey = $relatedModel->getModelForeignKeyName();
        }

        return new \BareMetalPHP\Database\Relations\BelongsToMany(
            $this,
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }

    /**
     * Get the foreign key name for this model (for pivot tables)
     */
    protected function getModelForeignKeyName(): string
    {
        // Convert "App\Models\Project" -> "project" -> "project_id"
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . '_id';
    }

    /**
     * Define a has-many-through relationship
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        string $firstKey,
        string $secondKey,
        string $localKey = 'id',
        string $secondLocalKey = 'id'
    ): Collection {
        $localValue = $this->attributes[$localKey] ?? null;
        if ($localValue === null) {
            return new Collection();
        }

        // Get intermediate IDs
        /** @var Model $through */
        $throughModels = $through::query()
            ->where($firstKey, '=', $localValue)
            ->get();

        if ($throughModels->isEmpty()) {
            return new Collection();
        }

        $throughIds = $throughModels->pluck($secondLocalKey)->all();

        /** @var Model $related */
        return $related::query()
            ->where($secondKey, 'IN', $throughIds)
            ->get();
    }


    // Accessors

    public function setAttribute(string $key, mixed $value): void
    {
        // Mutator: setEmailAttribute($value)
        if ($this->hasSetMutator($key)) {
            $method = $this->getSetMutatorName($key);
            $this->{$method}($value);
            return;
        }
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        $array = $this->attributes;
        
        // Include loaded relationships
        foreach ($this->relations as $key => $relation) {
            if ($relation instanceof Collection) {
                $array[$key] = $relation->toArray();
            } elseif ($relation instanceof Model) {
                $array[$key] = $relation->toArray();
            } else {
                $array[$key] = $relation;
            }
        }
        
        return $array;
    }

    /**
     * Define a polymorphic one-to-many relationship
     * 
     * e.g. Post::morphMany(Comment::class, 'commentable')
     *      => commentable_type, commentable_id
     * @param string $related
     * @param string $name
     * @param string $locaKey
     * @param string $localKey
     * @return MorphMany
     */
    protected function morphMany(string $related, string $name, string $locaKey, string $localKey = 'id'): MorphMany
    {
        $typeColumn = $name . '_type';
        $idColumn = $name . '_id';

        return new MorphMany($this, $related, $typeColumn, $idColumn, $localKey);
    }
    protected function morphOne(string $related, string $name, string $localKey = 'id'): MorphOne
    {
        $typeColumn = $name . '_type';
        $idColumn   = $name . '_id';

        return new MorphOne($this, $related, $typeColumn, $idColumn, $localKey);
    }

    /**
     * Define a polymorphic inverse relationship.
     * 
     * e.g. Comment::morphTo('commentable')
     * @param string $name
     * @return MorphTo
     */
    protected function morphTo(string $name): MorphTo
    {
        return new MorphTo($this, $name);
    }

    /**
     * Get the class name used to store this model in polymorphic relationship.
     * 
     * You can override this in child models to use a shorter alias
     * (similar to Laravel's morphMap)
     * @return string
     */
    public function getMorphClass(): string
    {
        return static::class;
    }
    protected function keyToStudly(string $key): string
    {
        // "full_name" -> "FullName"
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
    }

    protected function getGetMutatorName(string $key): string
    {
        return 'get' . $this->keyToStudly($key) . 'Attribute';
    }

    protected function getSetMutatorName(string $key): string
    {
        return 'set' . $this->keyToStudly($key) . 'Attribute';
    }

    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, $this->getGetMutatorName($key));
    }

    protected function hasSetMutator(string $key): bool
    {
        return method_exists($this, $this->getSetMutatorName($key));
    }

    public function __get(string $key): mixed
    {
        // 1) Attribute / accessor
        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttribute($key);
        }

        // 2) Loaded Relation
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // 3) Relationship-style method: User::posts(),User::profile(), etc
        if (method_exists($this, $key)) {
            try {
                // Set flag to get Relation object for __get (property access)
                static::$gettingRelationObject = true;
                $relation = $this->{$key}();
                static::$gettingRelationObject = false;

                if ($relation instanceof \BareMetalPHP\Database\Relations\BelongsToMany) {
                    $value = $relation->get();
                }

                // Any Relation subclasses: HasMany, HasOne, BelongsTo, etc.
                // Note: HasOne doesn't extend Relation, so we check for it separately
                elseif ($relation instanceof BaseRelation 
                    || $relation instanceof \BareMetalPHP\Database\Relations\HasOne
                    || $relation instanceof \BareMetalPHP\Database\Relations\HasMany
                    || $relation instanceof \BareMetalPHP\Database\Relations\BelongsTo) {
                    $value = $relation->getResults();
                } else {
                    // fallback -- allow methods to return Model/Collection directly
                    $value = $relation;
                }

                $this->relations[$key] = $value;
                return $value;

            } catch (\Throwable $e) {
                // In case of failure, cache null so we don't keep retrying
                $this->relations[$key] = null;
                return null;
            }
        }

        return null;
    }


    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    public function offsetGet(mixed $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function offsetExists(mixed $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->attributes[$key]);
    }
}