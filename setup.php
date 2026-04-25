<?php
/**
 * Light Pollution Monitoring System - Automated Setup Script
 * This script helps set up the database and verifies system requirements
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Light Pollution Monitoring System - Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; text-align: center; }
        h2 { color: #667eea; margin: 30px 0 15px 0; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #667eea; background: #f8f9ff; }
        .status { padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        button { background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        button:hover { background: #5a6fd8; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .code { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; margin: 10px 0; border: 1px solid #dee2e6; }
        .progress { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: #667eea; transition: width 0.3s ease; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Light Pollution Monitoring System Setup</h1>
        
        <?php
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        
        if ($step == 1) {
            echo '<h2>Step 1: System Requirements Check</h2>';
            
            echo '<div class="step">';
            echo '<h3>PHP Version Check</h3>';
            $phpVersion = PHP_VERSION;
            if (version_compare($phpVersion, '7.4.0', '>=')) {
                echo '<div class="success">PHP Version: ' . $phpVersion . ' (Compatible)</div>';
            } else {
                echo '<div class="error">PHP Version: ' . $phpVersion . ' (Requires 7.4 or higher)</div>';
            }
            echo '</div>';
            
            echo '<div class="step">';
            echo '<h3>Required Extensions</h3>';
            $extensions = ['mysqli', 'json', 'mbstring'];
            foreach ($extensions as $ext) {
                if (extension_loaded($ext)) {
                    echo '<div class="success">Extension ' . $ext . ': Loaded</div>';
                } else {
                    echo '<div class="error">Extension ' . $ext . ': Missing</div>';
                }
            }
            echo '</div>';
            
            echo '<div class="step">';
            echo '<h3>Directory Permissions</h3>';
            $dirs = [__DIR__, __DIR__ . '/api', __DIR__ . '/logs'];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (is_writable($dir)) {
                    echo '<div class="success">Directory ' . basename($dir) . ': Writable</div>';
                } else {
                    echo '<div class="warning">Directory ' . basename($dir) . ': Not writable</div>';
                }
            }
            echo '</div>';
            
            echo '<a href="?step=2"><button>Continue to Database Setup</button></a>';
        }
        
        elseif ($step == 2) {
            echo '<h2>Step 2: Database Setup</h2>';
            
            if (isset($_POST['setup_db'])) {
                $db_host = 'localhost';
                $db_user = 'root';
                $db_pass = '';
                $db_name = 'light_monitoring';
                
                try {
                    $conn = new mysqli($db_host, $db_user, $db_pass);
                    
                    // Create database
                    $conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
                    $conn->select_db($db_name);
                    
                    // Create tables
                    $conn->query("CREATE TABLE IF NOT EXISTS sensor_data (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lux FLOAT NOT NULL,
                        level VARCHAR(20) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_created_at (created_at),
                        INDEX idx_level (level)
                    )");
                    
                    $conn->query("CREATE TABLE IF NOT EXISTS system_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        log_type VARCHAR(50) NOT NULL,
                        message TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_created_at (created_at),
                        INDEX idx_log_type (log_type)
                    )");
                    
                    // Insert sample data
                    $conn->query("INSERT INTO sensor_data (lux, level) VALUES 
                        (25.5, 'Low'), (75.2, 'Moderate'), (180.8, 'High'), 
                        (45.0, 'Low'), (120.3, 'Moderate')");
                    
                    echo '<div class="success">Database setup completed successfully!</div>';
                    echo '<a href="?step=3"><button>Continue to Configuration</button></a>';
                    
                } catch (Exception $e) {
                    echo '<div class="error">Database setup failed: ' . $e->getMessage() . '</div>';
                    echo '<p>Please ensure MySQL is running and credentials are correct.</p>';
                }
                
            } else {
                echo '<div class="info">This will create the database and required tables.</div>';
                echo '<form method="post">';
                echo '<button type="submit" name="setup_db">Setup Database</button>';
                echo '</form>';
            }
        }
        
        elseif ($step == 3) {
            echo '<h2>Step 3: Configuration</h2>';
            
            echo '<div class="step">';
            echo '<h3>ESP32 Configuration</h3>';
            echo '<div class="code">';
            echo '// WiFi Configuration<br>';
            echo 'const char* ssid = "YOUR_WIFI_NETWORK";<br>';
            echo 'const char* password = "YOUR_WIFI_PASSWORD";<br><br>';
            echo '// Server Configuration<br>';
            echo 'const char* serverName = "http://' . $_SERVER['SERVER_ADDR'] . '/hayag/api/insert.php";';
            echo '</div>';
            echo '<p>Update these values in the ESP32 code before uploading.</p>';
            echo '</div>';
            
            echo '<div class="step">';
            echo '<h3>Test API Endpoints</h3>';
            echo '<p><a href="api/health_check.php" target="_blank">Health Check</a></p>';
            echo '<p><a href="api/get_data.php" target="_blank">Get Data</a></p>';
            echo '</div>';
            
            echo '<div class="success">Setup completed! Your system is ready to use.</div>';
            echo '<a href="index.php"><button>Go to Dashboard</button></a>';
        }
        ?>
        
        <div class="info" style="margin-top: 30px;">
            <h3>Need Help?</h3>
            <p>Check the README.md file for detailed instructions and troubleshooting.</p>
        </div>
    </div>
</body>
</html>
