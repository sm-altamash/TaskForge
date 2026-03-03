<?php
/**
 * Application Configuration
 *
 * General application settings loaded from .env.
 */

// -- Application Settings --
define('APP_NAME',    getenv('APP_NAME')  ?: 'TaskForge');
define('APP_URL',     getenv('APP_URL')   ?: 'http://localhost/TaskForge/public');
define('ENVIRONMENT', getenv('APP_ENV')   ?: 'development');

// -- API Settings --
define('API_V1_PATH', '/api/v1');

// -- Error Reporting --
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// -- Gemini AI Settings --
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'models/gemini-2.5-flash');
