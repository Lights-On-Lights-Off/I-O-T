-- ===============================
-- LIGHT POLLUTION SYSTEM DATABASE
-- PRO VERSION - Enhanced Security, Performance, Analytics & Scalability
-- ===============================

CREATE DATABASE IF NOT EXISTS light_monitoring
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE light_monitoring;

-- ===============================
-- SENSOR DATA TABLE
-- ===============================
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    lux DECIMAL(10,2) NOT NULL,
    level ENUM('low','moderate','high') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_created_at (created_at),
    INDEX idx_level (level),
    INDEX idx_device (device_id),
    INDEX idx_device_time (device_id, created_at)
);

-- ===============================
-- SYSTEM LOGS TABLE
-- ===============================
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('INFO','WARNING','ERROR') NOT NULL,
    message TEXT NOT NULL,
    device_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_created_at (created_at),
    INDEX idx_log_type (log_type),
    INDEX idx_device_log (device_id)
);

-- ===============================
-- DEVICES TABLE (SCALABLE SYSTEM)
-- ===============================
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) UNIQUE,
    location VARCHAR(100),
    status ENUM('ONLINE','OFFLINE') DEFAULT 'ONLINE',
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_device (device_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
);

-- ===============================
-- SAMPLE DATA
-- ===============================
INSERT INTO sensor_data (device_id, lux, level) VALUES 
('ESP32_001', 25.5, 'Low'),
('ESP32_001', 75.2, 'Moderate'),
('ESP32_001', 180.8, 'High'),
('ESP32_001', 45.0, 'Low'),
('ESP32_001', 120.3, 'Moderate');

INSERT IGNORE INTO devices (device_id, location) VALUES
('ESP32_001', 'School Area');

-- ===============================
-- VIEW FOR DASHBOARD (FAST QUERY)
-- ===============================
CREATE OR REPLACE VIEW latest_readings AS
SELECT * FROM sensor_data
ORDER BY created_at DESC;

-- ===============================
-- ANALYTICS VIEWS
-- ===============================
CREATE OR REPLACE VIEW device_status AS
SELECT 
    d.device_id,
    d.location,
    d.status,
    d.last_seen,
    COUNT(sd.id) as total_readings,
    AVG(sd.lux) as avg_lux,
    MAX(sd.created_at) as last_reading
FROM devices d
LEFT JOIN sensor_data sd ON d.device_id = sd.device_id
GROUP BY d.device_id;

CREATE OR REPLACE VIEW hourly_averages AS
SELECT 
    device_id,
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
    AVG(lux) as avg_lux,
    COUNT(*) as reading_count,
    level
FROM sensor_data
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY device_id, DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00'), level
ORDER BY hour DESC;

-- ===============================
-- PERFORMANCE & MAINTENANCE
-- ===============================
-- Stored procedure for data cleanup
DROP PROCEDURE IF EXISTS cleanup_old_data;

DELIMITER //
CREATE PROCEDURE cleanup_old_data()
BEGIN
    DECLARE rows_deleted INT DEFAULT 0;

    DELETE FROM sensor_data 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

    SET rows_deleted = ROW_COUNT();

    DELETE FROM system_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

    INSERT INTO system_logs (log_type, message)
    VALUES ('INFO', CONCAT('Cleanup: ', rows_deleted, ' records deleted'));
END //
DELIMITER ;

-- ===============================
-- SHOW TABLE STRUCTURES
-- ===============================
DESCRIBE sensor_data;
DESCRIBE system_logs;
DESCRIBE devices;
