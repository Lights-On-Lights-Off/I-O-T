  /**
  * Light Pollution Monitoring System - ESP32 Production Code
  * Reads BH1750 sensor data and sends to PHP API via WiFi
  * 
  * Hardware Connections:
  * BH1750 -> ESP32
  * VCC -> 3.3V
  * GND -> GND  
  * SDA -> GPIO 21
  * SCL -> GPIO 22
  * ADDR -> GND (0x23)
  */

  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <Wire.h>
  #include <BH1750.h>
  #include <ArduinoJson.h>

  // WiFi Configuration
  const char* ssid = "";
  const char* password = "";

  // Server Configuration - Using test endpoint to verify connectivity
  const char* serverName = "http://        /hayag/api/test_connection.php";

  // Device Configuration
  const char* deviceId = "ESP32_001";  // Unique device identifier

  // Timing Configuration
  const unsigned long READING_INTERVAL = 5000;  // 5 seconds between readings
  const unsigned long WIFI_RETRY_INTERVAL = 10000; // 10 seconds between WiFi retries
  const unsigned long SENSOR_RETRY_INTERVAL = 2000; // 2 seconds between sensor retries
  const int MAX_WIFI_RETRIES = 5;
  const int MAX_SENSOR_RETRIES = 3;

  // Pin Definitions (for ESP32)
  #define LED_PIN 2
  #define STATUS_LED 2

  // Global Variables
  BH1750 lightMeter;
  unsigned long lastReadingTime = 0;
  unsigned long lastWifiRetryTime = 0;
  int wifiRetryCount = 0;
  int sensorRetryCount = 0;
  bool systemOnline = false;
  bool sensorSimulationMode = false;

  // System Status
  struct SystemStatus {
    bool wifiConnected;
    bool sensorWorking;
    bool apiWorking;
    unsigned long lastSuccessfulSend;
    int totalReadings;
    int failedReadings;
  };

  SystemStatus status = {false, false, false, 0, 0, 0};

  void setup() {
    Serial.begin(115200);
    Serial.println("\n=== Light Pollution Monitoring System ===");
    Serial.println("Starting initialization...");
    
    // Initialize pins
    pinMode(LED_PIN, OUTPUT);
    pinMode(STATUS_LED, OUTPUT);
    
    // Blink LED to indicate startup
    blinkLED(3, 200);
    
    // Initialize I2C
    Wire.begin(21, 22);
    Serial.println("I2C initialized on SDA=21, SCL=22");
    
    // Initialize BH1750 sensor
    if (initializeSensor()) {
      status.sensorWorking = true;
      Serial.println("BH1750 sensor initialized successfully");
    } else {
      status.sensorWorking = false;
      Serial.println("ERROR: BH1750 sensor initialization failed");
    }
    
    // Initialize WiFi
    initializeWiFi();
    
    Serial.println("Setup completed. Starting main loop...");
    Serial.println("System will send data every 5 seconds");
  }

  void loop() {
    unsigned long currentTime = millis();
    
    // Check WiFi connection
    if (WiFi.status() != WL_CONNECTED) {
      handleWiFiDisconnection();
    }
    
    // Take reading if interval has passed
    if (currentTime - lastReadingTime >= READING_INTERVAL) {
      lastReadingTime = currentTime;
      
      if (status.sensorWorking) {
        performReading();
      } else {
        // Sensor not available - system is offline
        systemOnline = false;
        Serial.println("SYSTEM OFFLINE - No sensor available");
        Serial.println("Connect BH1750 sensor to enable system");
        
        // Try to reinitialize sensor periodically
        sensorRetryCount++;
        if (sensorRetryCount >= MAX_SENSOR_RETRIES) {
          Serial.println("Attempting sensor reinitialization...");
          if (initializeSensor()) {
            status.sensorWorking = true;
            systemOnline = true;
            Serial.println("Sensor found - System ONLINE");
          } else {
            Serial.println("Sensor still not available");
            sensorRetryCount = 0;
            delay(10000); // Wait longer before retry
          }
        }
      }
    }
    
    // Status LED management
    updateStatusLED();
    
    // Small delay to prevent watchdog issues
    delay(100);
  }

  bool initializeSensor() {
    if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
      Serial.println("BH1750 initialized in CONTINUOUS_HIGH_RES_MODE");
      sensorSimulationMode = false;
      return true;
    } else {
      Serial.println("Failed to initialize BH1750, trying alternative mode...");
      if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE_2)) {
        Serial.println("BH1750 initialized in CONTINUOUS_HIGH_RES_MODE_2");
        sensorSimulationMode = false;
        return true;
      }
      
      // STRICT VALIDATION: Stop system if sensor not detected
      Serial.println(" ERROR: BH1750 sensor not detected!");
      Serial.println(" SYSTEM STOPPED - Connect BH1750 sensor to continue");
      Serial.println(" Connections: VCC→3.3V, GND→GND, SDA→GPIO21, SCL→GPIO22");
      
      // Blink LED rapidly to indicate hardware error
      while (true) {
        digitalWrite(STATUS_LED, HIGH);
        delay(100);
        digitalWrite(STATUS_LED, LOW);
        delay(100);
      }
      
      return false; // Never reached
    }
  }

  void initializeWiFi() {
    Serial.println("Connecting to WiFi network: " + String(ssid));
    
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
      delay(500);
      Serial.print(".");
      attempts++;
      
      // Blink LED during connection
      digitalWrite(STATUS_LED, !digitalRead(STATUS_LED));
    }
    
    if (WiFi.status() == WL_CONNECTED) {
      status.wifiConnected = true;
      wifiRetryCount = 0;
      Serial.println("\nWiFi connected successfully!");
      Serial.print("IP address: ");
      Serial.println(WiFi.localIP());
      Serial.print("Signal strength (RSSI): ");
      Serial.print(WiFi.RSSI());
      Serial.println(" dBm");
      
      // Solid LED to indicate WiFi connected
      digitalWrite(STATUS_LED, HIGH);
    } else {
      status.wifiConnected = false;
      Serial.println("\nFailed to connect to WiFi");
      Serial.println("Will retry in 10 seconds...");
      
      // Fast blink to indicate WiFi disconnected
      digitalWrite(STATUS_LED, LOW);
    }
  }

  void handleWiFiDisconnection() {
    status.wifiConnected = false;
    Serial.println("WiFi disconnected! Attempting to reconnect...");
    
    unsigned long currentTime = millis();
    if (currentTime - lastWifiRetryTime >= WIFI_RETRY_INTERVAL) {
      lastWifiRetryTime = currentTime;
      wifiRetryCount++;
      
      if (wifiRetryCount <= MAX_WIFI_RETRIES) {
        Serial.println("WiFi retry attempt: " + String(wifiRetryCount));
        initializeWiFi();
      } else {
        Serial.println("Max WiFi retries reached. Resetting system...");
        delay(5000);
        ESP.restart();
  }
}

void handleWiFiDisconnection() {
  status.wifiConnected = false;
  Serial.println("WiFi disconnected! Attempting to reconnect...");
  
  unsigned long currentTime = millis();
  if (currentTime - lastWifiRetryTime >= WIFI_RETRY_INTERVAL) {
    lastWifiRetryTime = currentTime;
    wifiRetryCount++;
    
    if (wifiRetryCount <= MAX_WIFI_RETRIES) {
      Serial.println("WiFi retry attempt: " + String(wifiRetryCount));
      initializeWiFi();
    } else {
      Serial.println("Max WiFi retries reached. Resetting system...");
      delay(5000);
      ESP.restart();
    }
  }
}

void performReading() {
  Serial.println("\n--- Taking Reading ---");
  
  // Read from physical sensor only
  float lux = lightMeter.readLightLevel();
  
  // STRICT VALIDATION: Check for invalid readings
  if (lux <= 0 || isnan(lux) || isinf(lux)) {
    Serial.println(" ERROR: Invalid sensor reading detected!");
    Serial.print(" RAW LUX: ");
    Serial.println(lux);
    Serial.println(" Check BH1750 connections and power supply");
    Serial.println(" Skipping this reading...");
    
    // Increment failed readings counter
    status.failedReadings++;
    
    // If too many failures, mark sensor as not working
    if (status.failedReadings > 5) {
      status.sensorWorking = false;
      systemOnline = false;
      Serial.println(" SENSOR MARKED AS FAILED - Too many invalid readings");
    }
    
    return; // Skip processing this reading
  }
  
  // Additional validation: Check for realistic light values
  if (lux > 100000) { // BH1750 max is around 100,000 lux
    Serial.println(" ERROR: Unrealistic light reading!");
    Serial.print(" LUX: ");
    Serial.println(lux);
    Serial.println(" Sensor may be damaged or disconnected");
    return;
  }
  
  // Valid reading detected
  Serial.println(" VALID SENSOR READING");
  Serial.print(" LUX: ");
  Serial.println(lux, 2);
  
  status.totalReadings++;
  status.failedReadings = 0; // Reset failure counter on success
  status.sensorWorking = true;
  
  if (lux >= 0 && lux < 65536) {  // Valid range for BH1750
    status.totalReadings++;
    
    Serial.print("Light intensity: ");
    Serial.print(lux, 1);
    Serial.println(" lux");
    
    // Determine pollution level
    String level;
    if (lux <= 50) {
      level = "low";
    } else if (lux <= 150) {
      level = "moderate";
    } else {
      level = "high";
    }
    
    Serial.print("Pollution level: ");
    Serial.println(level);
    
    // Send data to server
    if (status.wifiConnected) {
      if (sendDataToServer(lux, level, deviceId)) {
        status.lastSuccessfulSend = millis();
        status.apiWorking = true;
        systemOnline = true;
        
        // Success indicator
        blinkLED(2, 100);
      Serial.println(" lux");
      
      // Determine pollution level
      String level;
      if (lux <= 50) {
        level = "low";
      } else if (lux <= 150) {
        level = "moderate";
      } else {
        level = "high";
      }
      
      Serial.print("Pollution level: ");
      Serial.println(level);
      
      // Send data to server
      if (status.wifiConnected) {
        if (sendDataToServer(lux, level, deviceId)) {
          status.lastSuccessfulSend = millis();
          status.apiWorking = true;
          systemOnline = true;
          
          // Success indicator
          blinkLED(2, 100);
        } else {
          status.failedReadings++;
          status.apiWorking = false;
          systemOnline = false;
          
          // Error indicator
          blinkLED(5, 50);
        }
      } else {
        Serial.println("WiFi not connected - data not sent");
        status.failedReadings++;
        systemOnline = false;
      }
      
    } else {
      Serial.println("ERROR: Invalid sensor reading: " + String(lux));
      status.failedReadings++;
      status.sensorWorking = false;
      
      // Try to reinitialize sensor
      delay(SENSOR_RETRY_INTERVAL);
    }
    
    // Print system status
    printSystemStatus();
  }

  bool sendDataToServer(float lux, String level, String device_id) {
    WiFiClient client;
    HTTPClient http;
    
    Serial.println("=== ESP32 HTTP FIX ===");
    Serial.print("Server URL: ");
    Serial.println(serverName);
    
    // Begin connection
    if (!http.begin(client, serverName)) {
      Serial.println("HTTP begin failed");
      return false;
    }
    Serial.println("HTTP begin successful");
    
    // CRITICAL: Set correct headers for POST data
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("User-Agent", "ESP32-LightMonitor/1.0");
    http.setTimeout(10000);  // 10 second timeout
    http.setConnectTimeout(5000);  // 5 second connection timeout
    
    // Prepare POST data
    String postData = "device_id=" + device_id + "&lux=" + String(lux, 2) + "&level=" + level;
    
    Serial.print("POST data: ");
    Serial.println(postData);
    
    // Send POST request
    Serial.println("Sending POST request...");
    Serial.print("POST data length: ");
    Serial.println(postData.length());
    Serial.print("POST data: ");
    Serial.println(postData);
    
    int httpResponseCode = http.POST(postData);
    
    // CRITICAL: Show HTTP response code
    Serial.print("HTTP Response code: ");
    Serial.println(httpResponseCode);
    
    // Handle response
    String response = "";
    if (httpResponseCode > 0) {
      response = http.getString();
      Serial.print("Response: ");
      Serial.println(response);
      Serial.print("Response length: ");
      Serial.println(response.length());
    } else {
      Serial.print("HTTP Error: ");
      Serial.println(httpResponseCode);
      
      // Error analysis
      if (httpResponseCode == -1) {
        Serial.println("Connection refused - check server");
      } else if (httpResponseCode == -11) {
        Serial.println("Timeout - increase timeout");
      } else if (httpResponseCode == -3) {
        Serial.println("DNS lookup failed");
      }
    }
    
    http.end();
    
    // Success check
    bool success = (httpResponseCode == 200 && response.length() > 0);
    Serial.print("Result: ");
    Serial.println(success ? "SUCCESS" : "FAILED");
    
    }

  void updateStatusLED() {
    static unsigned long lastBlink = 0;
    static bool ledState = false;
    
    if (systemOnline) {
      // Slow blink when online
      if (millis() - lastBlink >= 2000) {
        lastBlink = millis();
        ledState = !ledState;
        digitalWrite(STATUS_LED, ledState);
      }
    } else {
      // Fast blink when offline
      if (millis() - lastBlink >= 500) {
        lastBlink = millis();
        ledState = !ledState;
        digitalWrite(STATUS_LED, ledState);
      }
    }
  }

  void blinkLED(int count, int delayMs) {
    for (int i = 0; i < count; i++) {
      digitalWrite(LED_PIN, HIGH);
      delay(delayMs);
      digitalWrite(LED_PIN, LOW);
      delay(delayMs);
    }
  }

  void printSystemStatus() {
    Serial.println("\n=== System Status ===");
    Serial.print("WiFi: ");
    Serial.println(status.wifiConnected ? "Connected" : "Disconnected");
    Serial.print("Sensor: ");
    Serial.println(status.sensorWorking ? "Working" : "Error");
    Serial.print("API: ");
    Serial.println(status.apiWorking ? "Working" : "Error");
    Serial.print("System: ");
    Serial.println(systemOnline ? "Online" : "Offline");
    Serial.print("Total readings: ");
    Serial.println(status.totalReadings);
    Serial.print("Failed readings: ");
    Serial.println(status.failedReadings);
    Serial.print("Success rate: ");
    Serial.print(status.totalReadings > 0 ? 
      (float)(status.totalReadings - status.failedReadings) / status.totalReadings * 100 : 0);
    Serial.println("%");
    Serial.println("====================\n");
  }

