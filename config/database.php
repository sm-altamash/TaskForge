<?php
/**
 * Database Configuration
 *
 * Reads credentials from the .env file in the project root.
 * Falls back to XAMPP defaults if values are missing.
 */

define('DB_HOST',     getenv('DB_HOST')     ?: '127.0.0.1');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'taskforge');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
