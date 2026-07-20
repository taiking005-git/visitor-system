<?php

/**
 * Database Migration Runner
 * 
 * This helper file provides an easy interface to run database migrations
 * for the Visitor System setup.
 * 
 * Usage:
 *   - CLI: php migrations/run_migrations.php
 *   - Web: Access migrations/run_migrations.php?action=migrate in browser (if enabled)
 * 
 * @package Visitor System
 * @version 1.0
 */

class MigrationRunner
{

    private $migrations_dir;
    private $db_connection;
    private $log_file;

    public function __construct($migrations_dir = __DIR__)
    {
        $this->migrations_dir = $migrations_dir;
        $this->log_file = $this->migrations_dir . '/migration.log';
    }

    /**
     * Set database connection
     * @param object $connection PDO or mysqli connection object
     */
    public function setConnection($connection)
    {
        $this->db_connection = $connection;
    }

    /**
     * Get all available migrations
     * @return array
     */
    public function getAvailableMigrations()
    {
        $migrations = [];
        $files = glob($this->migrations_dir . '/[0-9]*_*.php');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            $migrations[] = [
                'file' => $filename,
                'path' => $file,
                'name' => str_replace('.php', '', $filename)
            ];
        }

        return $migrations;
    }

    /**
     * Run a specific migration
     * @param string $migration_file
     * @return bool
     */
    public function runMigration($migration_file)
    {
        $file_path = $this->migrations_dir . '/' . $migration_file;

        if (!file_exists($file_path)) {
            $this->log("ERROR: Migration file not found: {$migration_file}");
            return false;
        }

        try {
            require $file_path;

            // Extract class name from filename
            $class_name = $this->getClassNameFromFile($migration_file);

            if (!class_exists($class_name)) {
                $this->log("ERROR: Migration class not found: {$class_name}");
                return false;
            }

            $migration = new $class_name($this->db_connection);

            if ($migration->up()) {
                $this->log("SUCCESS: Migration completed - {$migration_file}");
                return true;
            } else {
                $errors = $migration->getErrorsAsString();
                $this->log("FAILED: Migration {$migration_file} - {$errors}");
                return false;
            }
        } catch (Exception $e) {
            $this->log("EXCEPTION: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run all migrations
     * @return bool
     */
    public function runAllMigrations()
    {
        $migrations = $this->getAvailableMigrations();
        $failed = [];

        $this->log("========== Starting Migration Run ==========");
        $this->log("Total migrations found: " . count($migrations));

        foreach ($migrations as $migration) {
            echo "Running: {$migration['name']}...\n";

            if (!$this->runMigration($migration['file'])) {
                $failed[] = $migration['name'];
            }
        }

        $this->log("========== Migration Run Complete ==========");

        if (empty($failed)) {
            $this->log("All migrations completed successfully!");
            return true;
        } else {
            $this->log("Failed migrations: " . implode(', ', $failed));
            return false;
        }
    }

    /**
     * Extract class name from migration filename
     * @param string $filename
     * @return string
     */
    private function getClassNameFromFile($filename)
    {
        // Convert: 001_create_initial_schema.php -> Migration_001_CreateInitialSchema
        $name = str_replace('.php', '', $filename);
        $parts = explode('_', $name);

        $class_name = 'Migration_';
        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $class_name .= $part . '_';
            } else {
                $class_name .= ucwords(str_replace('_', ' ', $part));
                $class_name = str_replace(' ', '', $class_name);
            }
        }

        return rtrim($class_name, '_');
    }

    /**
     * Log a message
     * @param string $message
     */
    public function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";

        // Log to file
        @file_put_contents($this->log_file, $log_message, FILE_APPEND);

        // Also output to stdout if running from CLI
        if (php_sapi_name() === 'cli') {
            echo $log_message;
        }
    }

    /**
     * Get migration log file path
     * @return string
     */
    public function getLogFile()
    {
        return $this->log_file;
    }

    /**
     * Read migration log
     * @return string
     */
    public function readLog()
    {
        if (file_exists($this->log_file)) {
            return file_get_contents($this->log_file);
        }
        return "No log file found.";
    }
}

// Entry point for CLI and web
if (php_sapi_name() === 'cli' || !empty($_GET['action'])) {

    $action = php_sapi_name() === 'cli' ? 'run' : ($_GET['action'] ?? 'list');

    try {
        // Get database connection
        $db_path = dirname(__DIR__) . '/db.php';
        if (!file_exists($db_path)) {
            die("Error: Database configuration not found.\n");
        }

        require $db_path;

        $runner = new MigrationRunner(__DIR__);
        $runner->setConnection($conn);

        if ($action === 'run') {
            echo "Starting database migrations...\n";
            if ($runner->runAllMigrations()) {
                echo "All migrations completed successfully!\n";
                echo "Log file: " . $runner->getLogFile() . "\n";
            } else {
                echo "Some migrations failed. Check the log file for details.\n";
                echo "Log file: " . $runner->getLogFile() . "\n";
            }
        } elseif ($action === 'list') {
            $migrations = $runner->getAvailableMigrations();
            echo "Available migrations:\n";
            echo "=====================\n";
            foreach ($migrations as $m) {
                echo "  - {$m['name']}\n";
            }
        } elseif ($action === 'log') {
            echo "Migration Log:\n";
            echo "==============\n";
            echo $runner->readLog();
        } else {
            echo "Unknown action: {$action}\n";
            echo "Available actions: run, list, log\n";
        }
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
