<?php
header("Content-Type: application/json");

// DEBUG: Capture all request information
$debug = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'NOT_SET',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'NOT_SET',
    'raw_input' => file_get_contents("php://input"),
    'post_data' => $_POST,
    'get_data' => $_GET,
    'headers' => getallheaders()
];

// Read raw input first
$raw = file_get_contents("php://input");

// Try normal POST first
$device_id = $_POST['device_id'] ?? null;
$lux = $_POST['lux'] ?? null;
$level = $_POST['level'] ?? null;

// If POST is empty, try parsing raw input
if (!$device_id && $raw) {
    parse_str($raw, $data);
    $device_id = $data['device_id'] ?? null;
    $lux = $data['lux'] ?? null;
    $level = $data['level'] ?? null;
}

// Write debug info to file
file_put_contents("debug.txt", print_r($debug, true));

if (!$device_id || !$lux || !$level) {
    echo json_encode([
        "status" => "error",
        "message" => "POST DATA MISSING",
        "received_post" => $_POST,
        "raw" => $raw
    ]);
    exit;
}

// STRICT VALIDATION: Check for invalid sensor data
if (!is_numeric($lux) || $lux <= 0 || $lux > 100000) {
    echo json_encode([
        "status" => "error",
        "message" => "INVALID SENSOR DATA",
        "lux" => $lux,
        "error" => "Lux value must be between 1 and 100000"
    ]);
    exit;
}

// Validate device ID format
if (!preg_match('/^[A-Za-z0-9_\-]+$/', $device_id)) {
    echo json_encode([
        "status" => "error", 
        "message" => "INVALID DEVICE ID",
        "device_id" => $device_id
    ]);
    exit;
}

// Validate pollution level
$valid_levels = ['low', 'moderate', 'high'];
if (!in_array($level, $valid_levels)) {
    echo json_encode([
        "status" => "error",
        "message" => "INVALID POLLUTION LEVEL",
        "level" => $level,
        "valid_levels" => $valid_levels
    ]);
    exit;
}

// DB CONNECTION
$conn = new mysqli("localhost", "root", "", "light_monitoring");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "DB ERROR"
    ]);
    exit;
}

// INSERT
$stmt = $conn->prepare("INSERT INTO sensor_data (device_id, lux, level) VALUES (?, ?, ?)");
$stmt->bind_param("sds", $device_id, $lux, $level);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "device_id" => $device_id,
        "lux" => $lux,
        "level" => $level
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "INSERT FAILED"
    ]);
}

$stmt->close();
$conn->close();
?>
