<?php
/**
 * FA Print Configuration
 * Central configuration file for database, API settings, and constants
 */

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'faprint');

// API Configuration
define('API_BASE', '/api');
define('API_VERSION', '1.0.0');

// Security
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production');
define('SESSION_TIMEOUT', 86400); // 24 hours

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif']);

// Campus Coordinates (Default Location)
define('CAMPUS_LAT', -6.7924);
define('CAMPUS_LNG', 37.6662);

// Email Configuration (for OTP and notifications)
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@faprint.local');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'localhost');

// Application Settings
define('APP_NAME', 'FA Print');
define('APP_VERSION', '1.0.0');
define('DEMO_MODE', false); // Set to false for production

// Pricing Constants
define('BINDING_PRICE', 1000); // Fixed binding price in TSH

// Ensure uploads directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Error Reporting
if (DEMO_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Ensure logs directory exists
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
?>
