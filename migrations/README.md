# Database Migrations

This directory contains database migration files for setting up the Visitor System database.

## Overview

Migrations are PHP classes that manage database schema changes. They allow you to:

- Create a clean database schema without pre-populated data
- Keep track of database changes over time
- Easily replicate the database structure in different environments
- Roll back changes if needed

## Files

- **001_create_initial_schema.php** - Creates all base tables and relationships
- **run_migrations.php** - Migration runner utility

## Available Migrations

### 001_create_initial_schema.php

This migration creates all the required tables for the Visitor System:

**Tables Created:**

- `admin_availability_log` - Logs admin availability status changes
- `audit_log` - Tracks all system actions for security auditing
- `destinations` - Stores location/destination information
- `events` - Manages events and seminars
- `users` - User accounts and roles
- `keycards` - Physical access keycards
- `visitors` - Visitor records and check-ins
- `login_attempts` - Login attempt history
- `notifications` - System notifications for users
- `password_reset_tokens` - Password reset tokens
- `websocket_sessions` - Real-time connection sessions

All tables are created with:

- Proper data types and constraints
- Indexes for performance
- Foreign key relationships
- Default values where appropriate

## How to Use

### Option 1: Using CLI (Recommended for Setup)

Run all migrations from command line:

```bash
cd path/to/visitor-system/migrations
php run_migrations.php run
```

This will execute all pending migrations and display progress.

### Option 2: Running from PHP Code

Include and instantiate the migration directly:

```php
<?php
require_once 'migrations/001_create_initial_schema.php';
require_once 'db.php';

$migration = new Migration_001_CreateInitialSchema($conn);

if ($migration->up()) {
    echo "Database setup completed successfully!";
} else {
    echo "Setup failed: " . implode(", ", $migration->getErrors());
}
?>
```

### Option 3: Using the Migration Runner

```php
<?php
require_once 'migrations/run_migrations.php';
require_once 'db.php';

$runner = new MigrationRunner(__DIR__ . '/migrations');
$runner->setConnection($conn);

if ($runner->runAllMigrations()) {
    echo "All migrations completed!";
}
?>
```

## Available Commands

### List Migrations

```bash
php run_migrations.php list
```

### Run All Migrations

```bash
php run_migrations.php run
```

### View Migration Log

```bash
php run_migrations.php log
```

## Setup Instructions

### Step 1: Prepare Database

Create an empty database in MySQL:

```sql
CREATE DATABASE visitor_db;
```

### Step 2: Run Migrations

```bash
cd migrations
php run_migrations.php run
```

### Step 3: Verify

Check the database:

```sql
USE visitor_db;
SHOW TABLES;
```

You should see all the tables listed above.

## Migration Structure

Each migration file follows this structure:

```php
class Migration_NNN_DescriptionName {

    public function up() {
        // Create tables and indexes
    }

    public function down() {
        // Drop tables (for rollback)
    }

    public function getErrors() {
        // Return any errors encountered
    }
}
```

## Creating New Migrations

To create a new migration:

1. Create a new file: `00X_description_name.php`
2. Follow the class naming pattern: `Migration_00X_DescriptionName`
3. Implement `up()` and `down()` methods
4. Use `executeQuery()` helper for SQL execution
5. Place in the migrations directory

Example template:

```php
<?php

class Migration_002_AddTableName {

    private $connection;
    private $errors = [];

    public function __construct($db_connection = null) {
        $this->connection = $db_connection;
    }

    public function up() {
        $sql = "CREATE TABLE IF NOT EXISTS `table_name` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        return $this->executeQuery($sql, 'table_name');
    }

    public function down() {
        return $this->connection->query("DROP TABLE IF EXISTS `table_name`");
    }

    public function getErrors() {
        return $this->errors;
    }

    private function executeQuery($sql, $table_name) {
        try {
            $this->connection->query($sql);
            return true;
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return false;
        }
    }
}
?>
```

## Logging

Migration logs are saved to `migration.log` in the migrations directory. Check this file to troubleshoot any issues:

```bash
cat migration.log
```

## Database Connection

Migrations require a database connection. They look for `db.php` one directory level up from the migrations folder:

```
visitor-system/
├── db.php              ← Database connection
├── migrations/
│   ├── 001_create_initial_schema.php
│   ├── run_migrations.php
│   └── migration.log
```

Ensure your `db.php` exports a `$conn` variable with a valid PDO or mysqli connection.

## Troubleshooting

### "Database connection not available"

- Check that `db.php` exists and is accessible
- Verify the database server is running
- Confirm database credentials are correct

### "Table already exists"

- The migration checks for existing tables with `CREATE TABLE IF NOT EXISTS`
- To start fresh, drop the tables manually and re-run migrations

### "Foreign key constraint fails"

- Ensure tables are created in the correct order
- Check that referenced tables exist before constraints are added

### No log output

- Check that the migrations directory is writable
- Verify PHP has permissions to create `migration.log` file

## Best Practices

1. **Always test migrations** - Test in a development environment first
2. **Keep migrations small** - One logical change per migration
3. **Name clearly** - Use descriptive names: `add_column_name`, `create_table_name`
4. **Document changes** - Add comments explaining what each migration does
5. **Verify constraints** - Ensure foreign keys reference existing tables
6. **Check logs** - Review migration logs for any warnings or errors

## Support

For issues or questions about migrations, refer to the main project documentation or contact the development team.
