<?php
/**
 * Light Pollution Monitoring System - Enhanced API Endpoint
 * PRO VERSION: Supports device tracking, enhanced logging, and ENUM types
 */

require_once 'config.php';

header('Content-Type: application/json');
sendCORSHeaders();

// Check rate limiting
checkRateLimit('data_insert');

// Get POST data
$lux_raw = isset($_POST['lux']) ? $_POST['lux'] : null;
$device_id = isset($_POST['device_id']) ? trim($_POST['device_id']) : 'ESP32_001';

// Debug logging
systemLog('INFO', "Raw data received: lux=$lux_raw, device=$device_id");

// Validate and clean lux value
if ($lux_raw === null || $lux_raw === '') {
    systemLog('WARNING', "Empty lux value from device $device_id");
    $lux = 0;
} elseif (!is_numeric($lux_raw)) {
    systemLog('WARNING', "Non-numeric lux value: $lux_raw from device $device_id");
    $lux = 0;
} else {
    $lux = floatval($lux_raw);
    
    // Clamp to valid range instead of rejecting
    if ($lux < 0) {
        systemLog('WARNING', "Negative lux value: $lux, setting to 0");
        $lux = 0;
    }
    if ($lux > 65535) {
        systemLog('WARNING', "High lux value: $lux, clamping to 65535");
        $lux = 65535;
    }
}

// Final validation
if ($lux < 0 || $lux > 65535) {
    systemLog('ERROR', "Lux validation failed after cleaning: $lux");
    sendJsonResponse('error', 'Lux validation failed');
}

if (empty($device_id) || strlen($device_id) > 50) {
    systemLog('WARNING', "Invalid device_id: $device_id");
    sendJsonResponse('error', 'Invalid device_id');
}

// Get database connection
$conn = getDatabaseConnection();
if (!$conn) {
    systemLog('ERROR', 'Database connection failed');
    sendJsonResponse('error', 'Database connection failed');
}

// Classify light pollution level using config function
$level = classifyLightLevel($lux);

// Start transaction for data integrity
$conn->begin_transaction();

try {
    // Insert sensor data with device_id
    $stmt = $conn->prepare("INSERT INTO sensor_data (device_id, lux, level) VALUES (?, ?, ?)");
    $stmt->bind_param("sds", $device_id, $lux, $level);
    
    if (!$stmt->execute()) {
        throw new Exception("Sensor data insertion failed: " . $stmt->error);
    }
    
    // Update device status and last seen
    $device_stmt = $conn->prepare("INSERT INTO devices (device_id, location, status, last_seen) 
                                   VALUES (?, ?, 'ONLINE', NOW()) 
                                   ON DUPLICATE KEY UPDATE 
                                   status = 'ONLINE', 
                                   last_seen = NOW()");
    
    $default_location = 'Unknown Location';
    $device_stmt->bind_param("ss", $device_id, $default_location);
    
    if (!$device_stmt->execute()) {
        throw new Exception("Device update failed: " . $device_stmt->error);
    }
    
    // Log successful insertion with device tracking
    $log_message = "Data inserted successfully: device=$device_id, lux=$lux, level=$level";
    systemLog('INFO', $log_message);
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'status' => 'success',
        'message' => 'Data inserted successfully',
        'data' => [
            'device_id' => $device_id,
            'lux' => $lux,
            'level' => $level,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Direct JSON output to ensure response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
        $conn->rollback();
    }
    
    systemLog('ERROR', "Transaction failed: " . $e->getMessage());
    
    // Ensure error response is always sent
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database operation failed: ' . $e->getMessage()
    ]);
    exit();
}

$stmt->close();
$device_stmt->close();
$conn->close();
?>
