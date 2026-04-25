<?php

header("Content-Type: application/json");

// FORCE DEBUG LOG
file_put_contents("debug.txt", print_r($_POST, true));

$device_id = $_POST['device_id'] ?? null;
$lux = $_POST['lux'] ?? null;
$level = $_POST['level'] ?? null;

// DEBUG RESPONSE (IMPORTANT)
if (!$device_id || !$lux || !$level) {
    echo json_encode([
        "status" => "error",
        "message" => "POST DATA MISSING",
        "received" => $_POST
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
