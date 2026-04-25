<?php
/**
 * Light Pollution Monitoring System - Data Retrieval API
 */

require_once 'config.php';

header('Content-Type: application/json');
sendCORSHeaders();

$conn = getDatabaseConnection();
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit();
}

try {
    // Get most recent reading
    $stmt = $conn->prepare("SELECT device_id, lux, level, created_at FROM sensor_data ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $current_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get total readings count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sensor_data");
    $stmt->execute();
    $count_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get average lux
    $stmt = $conn->prepare("SELECT AVG(lux) as average FROM sensor_data");
    $stmt->execute();
    $avg_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get last 20 readings for chart
    $stmt = $conn->prepare("SELECT device_id, lux, level, created_at FROM sensor_data ORDER BY id DESC LIMIT 20");
    $stmt->execute();
    $chart_result = $stmt->get_result();
    $chart_data = [];
    while ($row = $chart_result->fetch_assoc()) {
        $chart_data[] = $row;
    }
    $chart_data = array_reverse($chart_data); // oldest to newest
    $stmt->close();

    // Get recent 10 readings for table
    $stmt = $conn->prepare("SELECT id, device_id, lux, level, created_at FROM sensor_data ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $table_result = $stmt->get_result();
    $table_data = [];
    while ($row = $table_result->fetch_assoc()) {
        $table_data[] = $row;
    }
    $stmt->close();

    // Get pollution level distribution
    $stmt = $conn->prepare("SELECT level, COUNT(*) as count FROM sensor_data GROUP BY level");
    $stmt->execute();
    $level_result = $stmt->get_result();
    $level_distribution = [];
    while ($row = $level_result->fetch_assoc()) {
        $level_distribution[$row['level']] = $row['count'];
    }
    $stmt->close();

    $response = [
        'status'            => 'success',
        'current_lux'       => $current_data ? floatval($current_data['lux']) : 0,
        'current_level'     => $current_data ? $current_data['level'] : 'No Data',
        'current_device'    => $current_data ? $current_data['device_id'] : 'Unknown',
        'current_time'      => $current_data ? $current_data['created_at'] : null,
        'total_readings'    => intval($count_data['total']),
        'average_lux'       => floatval($avg_data['average']),
        'chart_data'        => $chart_data,
        'recent_data'       => $table_data,
        'level_distribution'=> $level_distribution,
        'timestamp'         => date('Y-m-d H:i:s')
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>