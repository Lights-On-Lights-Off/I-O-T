<?php
/**
 * Light Pollution Monitoring System - Configuration File
 * Centralized configuration for easy maintenance
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'light_monitoring');

// System Configuration
define('SYSTEM_NAME', 'Light Pollution Monitoring System');
define('SYSTEM_VERSION', '1.0.0');
define('DATA_RETENTION_DAYS', 30); // Keep data for 30 days

// Light Pollution Classification
define('LUX_THRESHOLD_LOW', 50);
define('LUX_THRESHOLD_HIGH', 150);

// API Configuration
define('API_TIMEOUT', 30); // seconds
define('MAX_REQUEST_SIZE', 1024 * 1024); // 1MB

// Security Configuration
define('ENABLE_CORS', true);
define('ALLOWED_ORIGINS', ['*']);
define('RATE_LIMIT_REQUESTS', 100); // requests per minute
define('RATE_LIMIT_WINDOW', 60); // seconds

// Logging Configuration
define('ENABLE_LOGGING', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/../logs/system.log');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Timezone
date_default_timezone_set('UTC');

/**
 * Database connection helper
 */
function getDatabaseConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }
        
        // Set charset
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

/**
 * Classification helper - Validates ENUM values
 */
function classifyLightLevel($lux) {
    if ($lux <= LUX_THRESHOLD_LOW) {
        return "Low";
    } elseif ($lux <= LUX_THRESHOLD_HIGH) {
        return "Moderate";
    } else {
        return "High";
    }
}

/**
 * Validate ENUM value for level
 */
function validateLevel($level) {
    $valid_levels = ['Low', 'Moderate', 'High'];
    return in_array($level, $valid_levels);
}

/**
 * Validate ENUM value for log type
 */
function validateLogType($log_type) {
    $valid_types = ['INFO', 'WARNING', 'ERROR'];
    return in_array($log_type, $valid_types);
}

/**
 * Validate device status
 */
function validateDeviceStatus($status) {
    $valid_statuses = ['ONLINE', 'OFFLINE'];
    return in_array($status, $valid_statuses);
}

/**
 * Logging helper
 */
function systemLog($level, $message) {
    if (!ENABLE_LOGGING) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Write to log file
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Also log to database if available
    $conn = getDatabaseConnection();
    if ($conn) {
        $stmt = $conn->prepare("INSERT INTO system_logs (log_type, message) VALUES (?, ?)");
        $stmt->bind_param("ss", $level, $message);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * CORS helper
 */
function sendCORSHeaders() {
    if (ENABLE_CORS) {
        header('Access-Control-Allow-Origin: ' . implode(', ', ALLOWED_ORIGINS));
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}

/**
 * JSON response helper
 */
function sendJsonResponse($status, $message = '', $data = null) {
    header('Content-Type: application/json');
    sendCORSHeaders();
    
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

/**
 * Rate limiting helper
 */
function checkRateLimit($identifier = 'default') {
    // Simple rate limiting using file-based storage
    $rateFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier);
    $currentTime = time();
    
    // Clean old entries
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        $data = array_filter($data, function($timestamp) use ($currentTime) {
            return $currentTime - $timestamp < RATE_LIMIT_WINDOW;
        });
        
        if (count($data) >= RATE_LIMIT_REQUESTS) {
            sendJsonResponse('error', 'Rate limit exceeded');
        }
        
        $data[] = $currentTime;
    } else {
        $data = [$currentTime];
    }
    
    file_put_contents($rateFile, json_encode($data));
}

/**
 * Input validation helper
 */
function validateLuxValue($lux) {
    if (!is_numeric($lux)) {
        return false;
    }
    
    $lux = floatval($lux);
    return $lux >= 0 && $lux < 65536; // Valid range for BH1750
}

/**
 * Clean old data helper
 */
function cleanOldData() {
    $conn = getDatabaseConnection();
    if (!$conn) {
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM sensor_data WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", DATA_RETENTION_DAYS);
    $stmt->execute();
    
    $deleted = $stmt->affected_rows;
    if ($deleted > 0) {
        systemLog('INFO', "Cleaned $deleted old records");
    }
    
    $stmt->close();
}
?>
