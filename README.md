# I-O-T

# Light Pollution Monitoring System

A complete IoT-based environmental monitoring system using ESP32 and BH1750 light sensor with PHP & MySQL backend for real-time light pollution monitoring.

## System Overview

This system measures ambient light intensity (lux) using a BH1750 sensor connected to an ESP32 microcontroller, sends the data via WiFi to a PHP API, stores it in a MySQL database, and displays it on a web dashboard with real-time visualization.

## Features

- **Real-time Monitoring**: Continuous light intensity measurement every 5 seconds
- **Data Classification**: Automatic categorization (Low/Moderate/High pollution levels)
- **Web Dashboard**: Interactive dashboard with charts and statistics
- **Error Handling**: Comprehensive error handling and automatic recovery
- **Health Monitoring**: System health checks and logging
- **Data Retention**: Configurable data retention policies
- **Security**: Input validation, rate limiting, and secure database operations

## Hardware Requirements

### Components
- ESP32 Development Board
- BH1750 Light Sensor (I2C)
- Jumper Wires
- Breadboard (optional)
- USB Cable for ESP32 programming

### Wiring Connections

```
BH1750 Sensor    ->    ESP32
VCC              ->    3.3V
GND              ->    GND
SDA              ->    GPIO 21
SCL              ->    GPIO 22
ADDR             ->    GND (sets I2C address to 0x23)
```

## Software Requirements

### Server Side
- XAMPP/WAMP/MAMP (Apache + MySQL + PHP)
- PHP 7.4+ with extensions:
  - mysqli
  - json
  - mbstring

### ESP32 Development
- Arduino IDE 1.8.19+ or PlatformIO
- ESP32 Board Manager
- Required Libraries:
  - WiFi (built-in)
  - HTTPClient (built-in)
  - Wire (built-in)
  - BH1750 by Christopher Laws
  - ArduinoJson by Benoit Blanchon

## Installation Guide

### 1. Database Setup

1. Start XAMPP and ensure Apache and MySQL are running
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Execute the SQL setup file:

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS light_monitoring;

-- Use the database
USE light_monitoring;

-- Create sensor_data table
CREATE TABLE IF NOT EXISTS sensor_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lux FLOAT NOT NULL,
    level VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_level (level)
);

-- Create system_logs table for debugging
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_log_type (log_type)
);
```

Or simply import the `database_setup.sql` file.

### 2. Web Application Setup

1. Copy all files to `C:\xampp\htdocs\hayag\` (or your web server root)
2. Ensure the following directory structure:
```
hayag/
|-- api/
|   |-- config.php
|   |-- insert.php
|   |-- get_data.php
|   |-- health_check.php
|-- index.php
|-- database_setup.sql
|-- README.md
|-- esp32_light_monitor.ino
```

3. Create logs directory:
```bash
mkdir C:\xampp\htdocs\hayag\logs
chmod 755 C:\xampp\htdocs\hayag\logs
```

4. Test the web dashboard:
   - Open browser: `http://localhost/hayag`
   - You should see the dashboard interface

### 3. ESP32 Configuration

1. Open `esp32_light_monitor.ino` in Arduino IDE
2. Configure your WiFi credentials:
```cpp
const char* ssid = "YOUR_WIFI_NETWORK";
const char* password = "YOUR_WIFI_PASSWORD";
```

3. Configure your server IP address:
```cpp
const char* serverName = "http://YOUR_IP_ADDRESS/hayag/api/insert.php";
```
   - Replace `YOUR_IP_ADDRESS` with your computer's IP address
   - Find your IP by running `ipconfig` in Windows Command Prompt

4. Install required libraries in Arduino IDE:
   - Sketch > Include Library > Manage Libraries
   - Search and install:
     - "BH1750" by Christopher Laws
     - "ArduinoJson" by Benoit Blanchon

5. Select ESP32 board:
   - Tools > Board > ESP32 Arduino > ESP32 Dev Module
   - Select correct COM port

6. Upload the code to ESP32

## Configuration Options

### Light Pollution Classification

Edit `api/config.php` to adjust thresholds:

```php
define('LUX_THRESHOLD_LOW', 50\);     // Low: 0-50 lux
define('LUX_THRESHOLD_HIGH', 150\);  // High: 151+ lux
```

### Data Retention

Configure how long to keep data:

```php
define('DATA_RETENTION_DAYS', 30); // Keep data for 30 days
```

### Reading Intervals

In `esp32_light_monitor.ino`:

```cpp
const unsigned long READING_INTERVAL = 5000;  // 5 seconds between readings
```

## API Endpoints

### Data Insertion
- **URL**: `/api/insert.php`
- **Method**: POST
- **Parameters**: `lux` (float)
- **Response**: JSON with status and message

### Data Retrieval
- **URL**: `/api/get_data.php`
- **Method**: GET
- **Response**: JSON with current readings, statistics, and historical data

### Health Check
- **URL**: `/api/health_check.php`
- **Method**: GET
- **Response**: JSON with system health status

## Troubleshooting

### Common Issues

#### ESP32 Not Connecting to WiFi
- Check WiFi credentials in the code
- Ensure WiFi network is available
- Try moving closer to router
- Check for interference

#### BH1750 Sensor Not Working
- Verify wiring connections
- Check I2C address (should be 0x23)
- Ensure 3.3V power supply
- Try different sensor resolution modes

#### API Not Receiving Data
- Check server IP address in ESP32 code
- Ensure XAMPP Apache is running
- Verify PHP error logs
- Test API manually with browser or curl

#### Database Issues
- Check MySQL service status
- Verify database and table creation
- Check database credentials in `config.php`
- Review MySQL error logs

### Debug Mode

Enable detailed logging by setting in `api/config.php`:

```php
define('LOG_LEVEL', 'DEBUG');
ini_set('display_errors', 1);
```

### Testing the API

Test data insertion with curl:
```bash
curl -X POST -d "lux=75.5" http://localhost/hayag/api/insert.php
```

Test data retrieval:
```bash
curl http://localhost/hayag/api/get_data.php
```

Test health check:
```bash
curl http://localhost/hayag/api/health_check.php
```

## System Architecture

```
[BH1750 Sensor] 
       |
       v
[ESP32 Microcontroller]
       | (WiFi HTTP POST)
       v
[PHP REST API]
       |
       v
[MySQL Database]
       |
       v
[Web Dashboard]
```

## Data Flow

1. **Sensor Reading**: BH1750 measures light intensity (lux)
2. **Data Processing**: ESP32 processes and validates reading
3. **WiFi Transmission**: HTTP POST request to PHP API
4. **Data Storage**: PHP API inserts data into MySQL database
5. **Classification**: Automatic pollution level assignment
6. **Visualization**: Web dashboard displays real-time data

## Security Considerations

- Input validation on all API endpoints
- Prepared statements for database operations
- Rate limiting to prevent abuse
- CORS configuration for API access
- Error logging without sensitive information exposure

## Performance Optimization

- Database indexes on timestamp and level columns
- Efficient JSON responses
- Minimal data transfer
- Connection pooling in PHP
- Automatic data cleanup

## Future Enhancements

- Mobile app for remote monitoring
- Email/SMS alerts for high pollution levels
- Multiple sensor support
- Geographic mapping
- Machine learning for pattern recognition
- Integration with weather APIs

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review system logs
3. Test individual components
4. Verify network connectivity
5. Check hardware connections

## License

This project is open source and available under the MIT License.
