<?php

/**
 * Visitor System - Database Setup Helper
 * 
 * This script provides a quick and easy way to set up the database
 * during initial installation or deployment.
 * 
 * Usage:
 *   - CLI: php setup_database.php
 *   - Web: Access setup_database.php?setup=true in browser
 * 
 * @package Visitor System
 * @version 1.0
 */

class DatabaseSetup
{

    private $db_config = [];
    private $connection = null;
    private $errors = [];
    private $warnings = [];
    private $success_messages = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load database configuration
     */
    private function loadConfig()
    {
        $config_path = __DIR__ . '/db.php';

        if (!file_exists($config_path)) {
            $this->errors[] = "Database configuration file not found at {$config_path}";
            return false;
        }

        // Capture the connection
        ob_start();
        require $config_path;
        ob_end_clean();

        if (isset($conn)) {
            $this->connection = $conn;
            return true;
        }

        $this->errors[] = "Failed to load database connection from db.php";
        return false;
    }

    /**
     * Perform complete database setup
     * @return bool
     */
    public function setup()
    {
        echo $this->getHeader();

        // Step 1: Verify connection
        if (!$this->verifyConnection()) {
            echo $this->renderErrors();
            return false;
        }
        $this->success_messages[] = "✓ Database connection verified";

        // Step 2: Create migrations directory structure
        if (!$this->setupMigrationsDirectory()) {
            echo $this->renderErrors();
            return false;
        }
        $this->success_messages[] = "✓ Migrations directory verified";

        // Step 3: Run migrations
        if (!$this->runMigrations()) {
            echo $this->renderErrors();
            return false;
        }
        $this->success_messages[] = "✓ Database schema created";

        // Step 4: Verify schema
        if (!$this->verifySchema()) {
            echo $this->renderWarnings();
            // Don't fail on warnings
        } else {
            $this->success_messages[] = "✓ Schema verification passed";
        }

        // Display results
        echo $this->renderSuccess();
        echo $this->renderSummary();

        return true;
    }

    /**
     * Verify database connection
     * @return bool
     */
    private function verifyConnection()
    {
        try {
            if (!$this->connection) {
                $this->errors[] = "No database connection available";
                return false;
            }

            // Test the connection
            if (method_exists($this->connection, 'ping')) {
                // MySQLi connection
                if (!$this->connection->ping()) {
                    $this->errors[] = "Database connection ping failed";
                    return false;
                }
            } else {
                // PDO connection - try a simple query
                $this->connection->query("SELECT 1");
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Database connection error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Setup migrations directory
     * @return bool
     */
    private function setupMigrationsDirectory()
    {
        $migrations_dir = __DIR__ . '/migrations';

        if (!is_dir($migrations_dir)) {
            if (!@mkdir($migrations_dir, 0755, true)) {
                $this->errors[] = "Failed to create migrations directory";
                return false;
            }
        }

        if (!is_writable($migrations_dir)) {
            $this->warnings[] = "Migrations directory may not be writable";
        }

        return true;
    }

    /**
     * Run all migrations
     * @return bool
     */
    private function runMigrations()
    {
        try {
            $migrations_dir = __DIR__ . '/migrations';

            // Get migration files
            $migration_file = $migrations_dir . '/001_create_initial_schema.php';

            if (!file_exists($migration_file)) {
                $this->errors[] = "Migration file not found: {$migration_file}";
                return false;
            }

            // Include and run migration
            require_once $migration_file;

            $migration = new Migration_001_CreateInitialSchema($this->connection);

            if (!$migration->up()) {
                $errors = $migration->getErrors();
                $this->errors[] = "Migration failed: " . implode("; ", $errors);
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Exception during migration: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Verify database schema
     * @return bool
     */
    private function verifySchema()
    {
        $required_tables = [
            'admin_availability_log',
            'audit_log',
            'destinations',
            'events',
            'users',
            'keycards',
            'visitors',
            'login_attempts',
            'notifications',
            'password_reset_tokens',
            'websocket_sessions'
        ];

        $existing_tables = $this->getTableList();
        $missing_tables = array_diff($required_tables, $existing_tables);

        if (!empty($missing_tables)) {
            $this->errors[] = "Missing tables: " . implode(", ", $missing_tables);
            return false;
        }

        return true;
    }

    /**
     * Get list of tables in current database
     * @return array
     */
    private function getTableList()
    {
        try {
            $tables = [];

            $result = $this->connection->query("SHOW TABLES");

            if ($result) {
                while ($row = $result->fetch_array(MYSQLI_NUM)) {
                    $tables[] = $row[0];
                }
            }

            return $tables;
        } catch (Exception $e) {
            $this->warnings[] = "Could not verify tables: " . $e->getMessage();
            return [];
        }
    }

    /**
     * Get HTML header
     * @return string
     */
    private function getHeader()
    {
        if (php_sapi_name() === 'cli') {
            return "\n╔════════════════════════════════════════╗\n" .
                "║   Visitor System - Database Setup      ║\n" .
                "╚════════════════════════════════════════╝\n\n";
        }

        return "<html>
                <head>
                    <title>Visitor System - Database Setup</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .container { max-width: 600px; margin: auto; }
                        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; }
                        .success { background: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 3px; border-left: 4px solid #28a745; }
                        .error { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 3px; border-left: 4px solid #dc3545; }
                        .warning { background: #fff3cd; color: #856404; padding: 10px; margin: 10px 0; border-radius: 3px; border-left: 4px solid #ffc107; }
                        .summary { background: #e7f3ff; padding: 15px; margin-top: 20px; border-radius: 3px; }
                    </style>
                </head>
                <body>
                <div class='container'>
                <div class='header'><h1>Visitor System - Database Setup</h1></div>";
    }

    /**
     * Render success messages
     * @return string
     */
    private function renderSuccess()
    {
        $output = "";

        if (php_sapi_name() === 'cli') {
            foreach ($this->success_messages as $msg) {
                $output .= $msg . "\n";
            }
        } else {
            foreach ($this->success_messages as $msg) {
                $output .= "<div class='success'>{$msg}</div>";
            }
        }

        return $output;
    }

    /**
     * Render error messages
     * @return string
     */
    private function renderErrors()
    {
        $output = "";

        if (php_sapi_name() === 'cli') {
            foreach ($this->errors as $error) {
                $output .= "✗ ERROR: {$error}\n";
            }
        } else {
            foreach ($this->errors as $error) {
                $output .= "<div class='error'>✗ ERROR: {$error}</div>";
            }
        }

        return $output;
    }

    /**
     * Render warning messages
     * @return string
     */
    private function renderWarnings()
    {
        $output = "";

        if (php_sapi_name() === 'cli') {
            foreach ($this->warnings as $warning) {
                $output .= "⚠ WARNING: {$warning}\n";
            }
        } else {
            foreach ($this->warnings as $warning) {
                $output .= "<div class='warning'>⚠ WARNING: {$warning}</div>";
            }
        }

        return $output;
    }

    /**
     * Render summary
     * @return string
     */
    private function renderSummary()
    {
        $html = "";

        if (php_sapi_name() === 'cli') {
            $html .= "\n" . str_repeat("─", 40) . "\n";
            $html .= "Setup Status: " . (empty($this->errors) ? "✓ SUCCESS" : "✗ FAILED") . "\n";
            $html .= str_repeat("─", 40) . "\n";
        } else {
            $status = empty($this->errors) ? "✓ SUCCESS" : "✗ FAILED";
            $html .= "<div class='summary'>";
            $html .= "<h3>Setup Status: {$status}</h3>";
            $html .= "<p>All required tables have been created successfully.</p>";
            $html .= "<p><strong>Next Steps:</strong></p>";
            $html .= "<ul>";
            $html .= "<li>Verify your database connection works</li>";
            $html .= "<li>Create default admin user (see documentation)</li>";
            $html .= "<li>Configure application settings</li>";
            $html .= "<li>Start using the system</li>";
            $html .= "</ul>";
            $html .= "</div>";
            $html .= "</div></body></html>";
        }

        return $html;
    }

    /**
     * Get errors
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Has errors
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
}

// Execute setup
if (php_sapi_name() === 'cli' || (!empty($_GET['setup']) && $_GET['setup'] === 'true')) {
    try {
        $setup = new DatabaseSetup();

        if ($setup->setup()) {
            exit(0); // Success
        } else {
            exit(1); // Failed
        }
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Display info page
    echo "<html>
            <head>
                <title>Visitor System - Setup</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .container { max-width: 600px; margin: auto; }
                    .info { background: #e7f3ff; padding: 15px; border-radius: 5px; }
                    .button { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; font-size: 16px; }
                    .button:hover { background: #218838; }
                </style>
            </head>
            <body>
            <div class='container'>
                <h1>Visitor System - Database Setup</h1>
                <div class='info'>
                    <p>Click the button below to run the database setup and create all required tables.</p>
                    <form method='GET'>
                        <input type='hidden' name='setup' value='true'>
                        <button type='submit' class='button'>Run Setup</button>
                    </form>
                </div>
            </div>
            </body>
            </html>";
}
