<?php

/**
 * Database Migration: Initial Schema
 * 
 * This migration creates all required tables for the Visitor System
 * without any pre-populated data.
 * 
 * Run this migration during initial setup to create the database structure.
 * 
 * @package Visitor System
 * @version 1.0
 * @author System
 */

class Migration_001_CreateInitialSchema
{

    private $connection;
    private $errors = [];
    private $success_count = 0;

    public function __construct($db_connection = null)
    {
        $this->connection = $db_connection;
    }

    /**
     * Execute the migration
     * @return bool
     */
    public function up()
    {
        try {
            if (!$this->connection) {
                $this->connection = $this->getConnection();
            }

            // Create tables in order of dependencies
            $this->createAdminAvailabilityLogTable();
            $this->createAuditLogTable();
            $this->createDestinationsTable();
            $this->createEventsTable();
            $this->createUsersTable();
            $this->createKeycardTable();
            $this->createVisitorsTable();
            $this->createLoginAttemptsTable();
            $this->createNotificationsTable();
            $this->createPasswordResetTokensTable();
            $this->createWebsocketSessionsTable();

            // Add constraints
            $this->addConstraints();

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Migration failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Rollback the migration
     * @return bool
     */
    public function down()
    {
        try {
            if (!$this->connection) {
                $this->connection = $this->getConnection();
            }

            $tables = [
                'websocket_sessions',
                'password_reset_tokens',
                'notifications',
                'login_attempts',
                'admin_availability_log',
                'visitors',
                'keycards',
                'users',
                'events',
                'destinations',
                'audit_log'
            ];

            foreach ($tables as $table) {
                $this->connection->query("DROP TABLE IF EXISTS `{$table}`");
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Rollback failed: " . $e->getMessage();
            return false;
        }
    }

    // ========== TABLE CREATION METHODS ==========

    private function createAdminAvailabilityLogTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `admin_availability_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `old_status` enum('available','busy','unavailable','away') DEFAULT NULL,
            `new_status` enum('available','busy','unavailable','away') NOT NULL,
            `status_message` varchar(255) DEFAULT NULL,
            `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user_date` (`user_id`,`changed_at`),
            KEY `idx_status` (`new_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'admin_availability_log');
    }

    private function createAuditLogTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `username` varchar(50) NOT NULL,
            `user_role` varchar(50) NOT NULL,
            `action` varchar(50) NOT NULL,
            `table_name` varchar(50) DEFAULT NULL,
            `record_id` int(11) DEFAULT NULL,
            `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
            `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
            `change_summary` text DEFAULT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` text DEFAULT NULL,
            `session_id` varchar(255) DEFAULT NULL,
            `status` enum('SUCCESS','FAILURE') NOT NULL,
            `error_message` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'audit_log');
    }

    private function createDestinationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `destinations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `range_start` int(11) NOT NULL,
            `range_end` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'destinations');
    }

    private function createEventsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `event_title` varchar(255) NOT NULL,
            `organizer_name` varchar(255) NOT NULL,
            `venue` varchar(255) DEFAULT NULL,
            `event_date` date NOT NULL,
            `time_slot` enum('morning','afternoon','fullday') NOT NULL,
            `description` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'events');
    }

    private function createUsersTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('super_admin','director','receptionist','destination_admin') NOT NULL,
            `destination_id` int(11) DEFAULT NULL,
            `must_change_password` tinyint(1) DEFAULT 1,
            `availability_status` enum('available','busy','unavailable','away') DEFAULT 'available',
            `status_message` varchar(255) DEFAULT NULL,
            `last_status_change` timestamp NULL DEFAULT NULL,
            `profile_photo` varchar(255) DEFAULT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            KEY `fk_admin_destination` (`destination_id`),
            KEY `idx_availability` (`availability_status`,`destination_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'users');
    }

    private function createKeycardTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `keycards` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `card_number` int(11) NOT NULL,
            `destination_id` int(11) NOT NULL,
            `is_assigned` tinyint(1) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `card_number` (`card_number`),
            KEY `destination_id` (`destination_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'keycards');
    }

    private function createVisitorsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `visitors` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `token` varchar(64) DEFAULT NULL,
            `expires_at` datetime DEFAULT NULL,
            `fullname` varchar(100) NOT NULL,
            `visitor_type` varchar(50) NOT NULL,
            `keycard` varchar(50) DEFAULT NULL,
            `faculty_organization` varchar(150) NOT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `purpose` varchar(255) NOT NULL,
            `visitor_message` text DEFAULT NULL,
            `preferred_return` datetime DEFAULT NULL,
            `admin_id` int(11) DEFAULT NULL,
            `availability_snapshot` enum('available','busy','unavailable','away') DEFAULT NULL,
            `status_message_snapshot` varchar(255) DEFAULT NULL,
            `queue_status` enum('pending','resolved','scheduled') DEFAULT 'pending',
            `admin_notes` text DEFAULT NULL,
            `resolved_at` datetime DEFAULT NULL,
            `destination` varchar(100) DEFAULT NULL,
            `time_in` timestamp NOT NULL DEFAULT current_timestamp(),
            `time_out` datetime DEFAULT NULL,
            `date` date DEFAULT NULL,
            `is_alternate` tinyint(1) DEFAULT 0,
            `keycard_id` int(11) DEFAULT NULL,
            `status` enum('active','inactive') DEFAULT 'active',
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `keycard_id` (`keycard_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'visitors');
    }

    private function createLoginAttemptsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(255) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `attempt_time` datetime NOT NULL DEFAULT current_timestamp(),
            `success` tinyint(4) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'login_attempts');
    }

    private function createNotificationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `notification_type` enum('visitor_arrival','system','alert','checkout') DEFAULT 'visitor_arrival',
            `visitor_id` int(11) DEFAULT NULL,
            `action_url` varchar(255) DEFAULT NULL,
            `priority` enum('low','normal','high') DEFAULT 'normal',
            `read_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `fk_notification_visitor` (`visitor_id`),
            KEY `idx_user_read` (`user_id`,`is_read`),
            KEY `idx_type` (`notification_type`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'notifications');
    }

    private function createPasswordResetTokensTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `token` varchar(64) NOT NULL,
            `expires_at` datetime NOT NULL,
            `used` tinyint(1) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `idx_token` (`token`),
            KEY `idx_expires` (`expires_at`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'password_reset_tokens');
    }

    private function createWebsocketSessionsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `websocket_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `session_id` varchar(64) NOT NULL,
            `connection_id` varchar(128) DEFAULT NULL,
            `connected_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `last_ping` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `session_id` (`session_id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_session` (`session_id`),
            KEY `idx_last_ping` (`last_ping`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->executeQuery($sql, 'websocket_sessions');
    }

    // ========== CONSTRAINT METHODS ==========

    private function addConstraints()
    {
        $constraints = [
            // admin_availability_log constraints
            "ALTER TABLE `admin_availability_log` ADD CONSTRAINT `admin_availability_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",

            // keycards constraints
            "ALTER TABLE `keycards` ADD CONSTRAINT `keycards_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`) ON DELETE CASCADE",

            // notifications constraints
            "ALTER TABLE `notifications` ADD CONSTRAINT `fk_notification_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL",
            "ALTER TABLE `notifications` ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",

            // password_reset_tokens constraints
            "ALTER TABLE `password_reset_tokens` ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE",

            // users constraints
            "ALTER TABLE `users` ADD CONSTRAINT `fk_admin_destination` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`) ON DELETE SET NULL",

            // visitors constraints
            "ALTER TABLE `visitors` ADD CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`keycard_id`) REFERENCES `keycards` (`id`) ON DELETE SET NULL",

            // websocket_sessions constraints
            "ALTER TABLE `websocket_sessions` ADD CONSTRAINT `websocket_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE"
        ];

        foreach ($constraints as $constraint) {
            try {
                $this->connection->query($constraint);
            } catch (Exception $e) {
                // Constraint might already exist, continue
                error_log("Constraint warning: " . $e->getMessage());
            }
        }
    }

    // ========== HELPER METHODS ==========

    private function executeQuery($sql, $table_name)
    {
        try {
            $this->connection->query($sql);
            $this->success_count++;
            echo "[OK] Table '$table_name' created successfully.\n";
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Failed to create table '$table_name': " . $e->getMessage();
            echo "[ERROR] Failed to create table '$table_name': " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function getConnection()
    {
        // This assumes your db.php exists and provides a connection
        if (file_exists(__DIR__ . '/../db.php')) {
            require __DIR__ . '/../db.php';
            if (isset($conn)) {
                return $conn;
            }
        }
        throw new Exception('Database connection not available');
    }

    // ========== GETTER METHODS ==========

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->success_count;
    }

    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    public function getErrorsAsString()
    {
        return implode("\n", $this->errors);
    }
}

// Usage example:
if (php_sapi_name() === 'cli' || (isset($_GET['run_migration']) && $_GET['run_migration'] === 'true')) {
    echo "=== Visitor System Database Migration ===\n";
    echo "Migration: 001_create_initial_schema\n";
    echo "========================================\n\n";

    try {
        // Include your database connection
        $db_path = __DIR__ . '/../db.php';
        if (!file_exists($db_path)) {
            die("Error: Database configuration file not found at {$db_path}\n");
        }

        require $db_path;

        $migration = new Migration_001_CreateInitialSchema($conn);

        echo "Running migration...\n";
        if ($migration->up()) {
            echo "\n✓ Migration completed successfully!\n";
            echo "Tables created: " . $migration->getSuccessCount() . "\n";
            exit(0);
        } else {
            echo "\n✗ Migration failed!\n";
            echo $migration->getErrorsAsString() . "\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "\n✗ Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
