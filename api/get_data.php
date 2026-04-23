<?php
/**
 * Light Pollution Monitoring System - Enhanced Data Retrieval API
 * PRO VERSION: Supports multiple devices, analytics views, and ENUM types
 */

require_once 'config.php';

header('Content-Type: application/json');
sendCORSHeaders();

// Check rate limiting
checkRateLimit('data_retrieval');

// Get database connection
$conn = getDatabaseConnection();
if (!$conn) {
    systemLog('ERROR', 'Database connection failed in get_data.php');
    sendJsonResponse('error', 'Database connection failed');
}

try {
    // Get optional device filter
    $device_filter = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
    
    // Get current reading (most recent) - using device filter if provided
    $current_query = "SELECT device_id, lux, level, created_at FROM sensor_data";
    if ($device_filter) {
        $current_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($current_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($current_query);
    }
    $current_query .= " ORDER BY id DESC LIMIT 1";
    
    $stmt->execute();
    $current_result = $stmt->get_result();
    $current_data = $current_result->fetch_assoc();
    $stmt->close();

    // Get device statistics
    $device_stats_query = "SELECT * FROM device_status";
    if ($device_filter) {
        $device_stats_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($device_stats_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($device_stats_query);
    }
    
    $stmt->execute();
    $device_stats_result = $stmt->get_result();
    $device_stats = [];
    while ($row = $device_stats_result->fetch_assoc()) {
        $device_stats[] = $row;
    }
    $stmt->close();

    // Get total readings count
    $count_query = "SELECT COUNT(*) as total FROM sensor_data";
    if ($device_filter) {
        $count_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($count_query);
    }
    
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    $stmt->close();

    // Get average lux
    $avg_query = "SELECT AVG(lux) as average FROM sensor_data";
    if ($device_filter) {
        $avg_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($avg_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($avg_query);
    }
    
    $stmt->execute();
    $avg_result = $stmt->get_result();
    $avg_data = $avg_result->fetch_assoc();
    $stmt->close();

    // Get last 20 readings for chart - using optimized view
    $chart_query = "SELECT device_id, lux, level, created_at FROM latest_readings";
    if ($device_filter) {
        $chart_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($chart_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($chart_query);
    }
    
    $stmt->execute();
    $chart_result = $stmt->get_result();
    $chart_data = [];
    while ($row = $chart_result->fetch_assoc()) {
        $chart_data[] = $row;
    }
    $stmt->close();
    
    // Reverse to show oldest to newest
    $chart_data = array_reverse($chart_data);

    // Get recent data for table (last 10 entries)
    $table_query = "SELECT id, device_id, lux, level, created_at FROM sensor_data";
    if ($device_filter) {
        $table_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($table_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($table_query);
    }
    $table_query .= " ORDER BY id DESC LIMIT 10";
    
    $stmt->execute();
    $table_result = $stmt->get_result();
    $table_data = [];
    while ($row = $table_result->fetch_assoc()) {
        $table_data[] = $row;
    }
    $stmt->close();

    // Get pollution level distribution
    $level_query = "SELECT level, COUNT(*) as count FROM sensor_data";
    if ($device_filter) {
        $level_query .= " WHERE device_id = ?";
        $stmt = $conn->prepare($level_query);
        $stmt->bind_param("s", $device_filter);
    } else {
        $stmt = $conn->prepare($level_query);
    }
    $level_query .= " GROUP BY level";
    
    $stmt->execute();
    $level_result = $stmt->get_result();
    $level_distribution = [];
    while ($row = $level_result->fetch_assoc()) {
        $level_distribution[$row['level']] = $row['count'];
    }
    $stmt->close();

    // Prepare response
    $response = [
        'status' => 'success',
        'current_lux' => $current_data ? floatval($current_data['lux']) : 0,
        'current_level' => $current_data ? $current_data['level'] : 'No Data',
        'current_device' => $current_data ? $current_data['device_id'] : 'Unknown',
        'total_readings' => intval($count_data['total']),
        'average_lux' => floatval($avg_data['average']),
        'chart_data' => $chart_data,
        'recent_data' => $table_data,
        'device_stats' => $device_stats,
        'level_distribution' => $level_distribution,
        'device_filter' => $device_filter,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);
    
} catch (Exception $e) {
    systemLog('ERROR', "Error in get_data.php: " . $e->getMessage());
    sendJsonResponse('error', 'Data retrieval failed: ' . $e->getMessage());
}

$conn->close();
?>
