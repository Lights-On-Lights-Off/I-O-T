<?php
/**
 * Light Pollution Monitoring System - Health Check API
 * Provides system health status for monitoring
 */

require_once 'config.php';

header('Content-Type: application/json');
sendCORSHeaders();

// Check rate limiting
checkRateLimit('health_check');

// Initialize health status
$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check database connection
$dbCheck = ['name' => 'database', 'status' => 'unknown'];
$conn = getDatabaseConnection();
if ($conn) {
    // Test database query
    $result = $conn->query("SELECT 1");
    if ($result) {
        $dbCheck['status'] = 'healthy';
        $dbCheck['message'] = 'Database connection successful';
        
        // Check table existence
        $tables = ['sensor_data', 'system_logs'];
        $missingTables = [];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows == 0) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            $dbCheck['status'] = 'warning';
            $dbCheck['message'] = 'Missing tables: ' . implode(', ', $missingTables);
        }
    } else {
        $dbCheck['status'] = 'error';
        $dbCheck['message'] = 'Database query failed';
    }
} else {
    $dbCheck['status'] = 'error';
    $dbCheck['message'] = 'Database connection failed';
}
$health['checks'][] = $dbCheck;

// Check recent data activity
$dataCheck = ['name' => 'data_activity', 'status' => 'unknown'];
if ($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sensor_data WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $recentCount = $row['count'];
    
    if ($recentCount > 0) {
        $dataCheck['status'] = 'healthy';
        $dataCheck['message'] = "$recentCount records in last hour";
    } else {
        $dataCheck['status'] = 'warning';
        $dataCheck['message'] = 'No data received in last hour';
    }
    $dataCheck['recent_count'] = $recentCount;
    $stmt->close();
} else {
    $dataCheck['status'] = 'error';
    $dataCheck['message'] = 'Cannot check data activity';
}
$health['checks'][] = $dataCheck;

// Check disk space
$diskCheck = ['name' => 'disk_space', 'status' => 'unknown'];
$logDir = dirname(LOG_FILE);
$freeSpace = disk_free_space($logDir);
$totalSpace = disk_total_space($logDir);
$usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

if ($usedPercent < 80) {
    $diskCheck['status'] = 'healthy';
    $diskCheck['message'] = sprintf("%.1f%% disk used", $usedPercent);
} elseif ($usedPercent < 90) {
    $diskCheck['status'] = 'warning';
    $diskCheck['message'] = sprintf("%.1f%% disk used", $usedPercent);
} else {
    $diskCheck['status'] = 'error';
    $diskCheck['message'] = sprintf("%.1f%% disk used", $usedPercent);
}
$diskCheck['free_space_gb'] = round($freeSpace / 1024 / 1024 / 1024, 2);
$diskCheck['used_percent'] = round($usedPercent, 1);
$health['checks'][] = $diskCheck;

// Check PHP version
$phpCheck = ['name' => 'php_version', 'status' => 'healthy'];
$phpVersion = PHP_VERSION;
if (version_compare($phpVersion, '7.4.0', '>=')) {
    $phpCheck['status'] = 'healthy';
    $phpCheck['message'] = "PHP $phpVersion (supported)";
} else {
    $phpCheck['status'] = 'warning';
    $phpCheck['message'] = "PHP $phpVersion (upgrade recommended)";
}
$phpCheck['version'] = $phpVersion;
$health['checks'][] = $phpCheck;

// Check required extensions
$extensionsCheck = ['name' => 'php_extensions', 'status' => 'healthy'];
$requiredExtensions = ['mysqli', 'json'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $extensionsCheck['status'] = 'healthy';
    $extensionsCheck['message'] = 'All required extensions loaded';
} else {
    $extensionsCheck['status'] = 'error';
    $extensionsCheck['message'] = 'Missing extensions: ' . implode(', ', $missingExtensions);
}
$extensionsCheck['missing'] = $missingExtensions;
$health['checks'][] = $extensionsCheck;

// Check log file writability
$logCheck = ['name' => 'log_file', 'status' => 'unknown'];
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        $logCheck['status'] = 'error';
        $logCheck['message'] = 'Cannot create log directory';
    }
}

if (is_writable($logDir)) {
    $logCheck['status'] = 'healthy';
    $logCheck['message'] = 'Log directory writable';
} else {
    $logCheck['status'] = 'error';
    $logCheck['message'] = 'Log directory not writable';
}
$logCheck['log_path'] = LOG_FILE;
$health['checks'][] = $logCheck;

// Overall system status
$overallStatus = 'healthy';
$warnings = 0;
$errors = 0;

foreach ($health['checks'] as $check) {
    if ($check['status'] === 'error') {
        $errors++;
    } elseif ($check['status'] === 'warning') {
        $warnings++;
    }
}

if ($errors > 0) {
    $overallStatus = 'error';
} elseif ($warnings > 0) {
    $overallStatus = 'warning';
}

$health['status'] = $overallStatus;
$health['summary'] = [
    'total_checks' => count($health['checks']),
    'healthy' => count(array_filter($health['checks'], function($c) { return $c['status'] === 'healthy'; })),
    'warnings' => $warnings,
    'errors' => $errors
];

// Log health check
systemLog('INFO', "Health check: $overallStatus (Errors: $errors, Warnings: $warnings)");

echo json_encode($health, JSON_PRETTY_PRINT);
?>
