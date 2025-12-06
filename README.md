# Bare Metal PHP Framework

A lightweight, educational PHP framework with service container, routing, ORM, migrations, and more.

## Features

- 🎯 **Service Container** - Dependency injection and service management
- 🛣️ **Routing** - Clean, simple routing with middleware support
- 🗄️ **ORM** - Active Record style ORM with relationships (hasOne, hasMany, belongsTo, belongsToMany)
- 📊 **Migrations** - Database version control and schema management
- 🎨 **Views** - Simple templating engine with blade-like syntax
- 🔐 **Authentication** - Built-in authentication helpers
- 🧪 **Testing** - PHPUnit test suite included
- ⚡ **CLI Tools** - Built-in console commands for common tasks

## Requirements

- PHP 8.0+
- PDO extension
- SQLite, MySQL, or PostgreSQL support

## Installation

Install via Composer:

```bash
composer require elliotanderson/phpframework
```

## Quick Start

### Creating a New Project

The easiest way to get started is to use the project skeleton:

```bash
composer create-project elliotanderson/baremetal my-app
```

### Manual Setup

1. **Require the framework**:

```bash
composer require elliotanderson/phpframework
```

2. **Set up your application structure**:

```
my-app/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   └── Models/
├── bootstrap/
│   └── app.php
├── config/
│   └── database.php
├── public/
│   └── index.php
├── routes/
│   └── web.php
└── composer.json
```

3. **Create a route** (`routes/web.php`):

```php
use Framework\Routing\Router;
use Framework\Http\Response;

return function (Router $router): void {
    $router->get('/', function () {
        return new Response('Hello, World!');
    });
};
```

4. **Bootstrap your application** (`bootstrap/app.php`):

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Framework\Application;

$app = new Application(__DIR__ . '/..');
$app->registerProviders([
    Framework\Providers\ConfigServiceProvider::class,
    Framework\Providers\DatabaseServiceProvider::class,
    Framework\Providers\RoutingServiceProvider::class,
    Framework\Providers\ViewServiceProvider::class,
]);

return $app;
```

5. **Create your entry point** (`public/index.php`):

```php
<?php

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```

## Usage Examples

### Routing

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);
```

### Models

```php
use Framework\Database\Model;

class User extends Model
{
    protected $table = 'users';
    
    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts;
```

### Database Migrations

```php
use Framework\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up($connection)
    {
        $this->createTable($connection, 'users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
    
    public function down($connection)
    {
        $this->dropTable($connection, 'users');
    }
}
```

### Views

```php
use Framework\View\View;

return View::make('welcome', [
    'name' => 'World'
]);
```

## CLI Commands

The framework includes a CLI tool (`mini`) with several commands:

- `php mini serve` - Start the development server
- `php mini migrate` - Run pending migrations
- `php mini migrate:rollback` - Rollback the last migration
- `php mini make:controller Name` - Create a new controller
- `php mini make:migration name` - Create a new migration

## Testing

```bash
composer test
# or
vendor/bin/phpunit
```

## Documentation

For detailed documentation, visit the [framework documentation](https://github.com/elliotanderson/phpframework).

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

