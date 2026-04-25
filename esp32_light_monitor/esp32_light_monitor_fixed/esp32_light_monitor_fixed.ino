/**
 * Light Pollution Monitoring System - ESP32
 * Reads BH1750 sensor data and sends to PHP API via WiFi
 *
 * Hardware Connections:
 * BH1750 -> ESP32
 * VCC    -> 3.3V
 * GND    -> GND
 * SDA    -> GPIO 21
 * SCL    -> GPIO 22
 * ADDR   -> GND (I2C address: 0x23)
 */
#include <WiFiClientSecure.h>  // add this at the top of the file

#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <BH1750.h>

// ─── WiFi Configuration ───────────────────────────────────────────────────────
const char* ssid     = "Wifi name
const char* password = "Your wifi password";

// ─── Server Configuration ─────────────────────────────────────────────────────
// TODO: Replace with your actual server IP or domain
const char* serverName = "serverlink"

// ─── Device Configuration ─────────────────────────────────────────────────────
const char* deviceId = "ESP32_001";

// ─── Timing Configuration ─────────────────────────────────────────────────────
const unsigned long READING_INTERVAL    = 5000;   // 5 seconds between readings
const unsigned long WIFI_RETRY_INTERVAL = 10000;  // 10 seconds between WiFi retries
const int           MAX_WIFI_RETRIES    = 5;
const int           MAX_SENSOR_RETRIES  = 3;

// ─── Pin Definitions ──────────────────────────────────────────────────────────
#define STATUS_LED 2  // Built-in LED on most ESP32 boards

// ─── Global Objects & Variables ───────────────────────────────────────────────
BH1750 lightMeter;

unsigned long lastReadingTime   = 0;
unsigned long lastWifiRetryTime = 0;
int           wifiRetryCount    = 0;
int           sensorRetryCount  = 0;
bool          systemOnline      = false;

// ─── System Status Struct ─────────────────────────────────────────────────────
struct SystemStatus {
  bool          wifiConnected;
  bool          sensorWorking;
  bool          apiWorking;
  unsigned long lastSuccessfulSend;
  int           totalReadings;
  int           failedReadings;
};

SystemStatus status = {false, false, false, 0, 0, 0};

// ─── Function Declarations ────────────────────────────────────────────────────
bool initializeSensor();
void initializeWiFi();
void handleWiFiDisconnection();
void performReading();
bool sendDataToServer(float lux, String level, String device_id);
void updateStatusLED();
void blinkLED(int count, int delayMs);
void printSystemStatus();


// SETUP

void setup() {
  Serial.begin(115200);
  Serial.println("\n=== Light Pollution Monitoring System ===");
  Serial.println("Starting initialization...");

  // Initialize LED pin
  pinMode(STATUS_LED, OUTPUT);
  blinkLED(3, 200);  // 3 blinks = startup indicator

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

  // Connect to WiFi
  initializeWiFi();

  Serial.println("Setup completed. Starting main loop...");
  Serial.println("Readings will be sent every 5 seconds.");
}


// LOOP

void loop() {
  unsigned long currentTime = millis();

  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    handleWiFiDisconnection();
  }

  // Take a reading every READING_INTERVAL ms
  if (currentTime - lastReadingTime >= READING_INTERVAL) {
    lastReadingTime = currentTime;

    if (status.sensorWorking) {
      performReading();
    } else {
      systemOnline = false;
      Serial.println("SYSTEM OFFLINE - No sensor available");
      Serial.println("Connect BH1750 sensor to enable system");

      // Periodically try to reinitialize the sensor
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
        }
      }
    }
  }

  // Update status LED
  updateStatusLED();

  delay(100);  // Small delay to prevent watchdog issues
}


// SENSOR INITIALIZATION

bool initializeSensor() {
  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE)) {
    Serial.println("BH1750 initialized in CONTINUOUS_HIGH_RES_MODE");
    return true;
  }

  Serial.println("Trying CONTINUOUS_HIGH_RES_MODE_2...");
  if (lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE_2)) {
    Serial.println("BH1750 initialized in CONTINUOUS_HIGH_RES_MODE_2");
    return true;
  }

  Serial.println("ERROR: BH1750 sensor not detected!");
  Serial.println("Check connections: VCC->3.3V, GND->GND, SDA->GPIO21, SCL->GPIO22");
  return false;
}


// WIFI INITIALIZATION

void initializeWiFi() {
  Serial.println("Connecting to WiFi: " + String(ssid));
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    digitalWrite(STATUS_LED, !digitalRead(STATUS_LED));  // Toggle LED
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    status.wifiConnected = true;
    wifiRetryCount = 0;
    Serial.println("\nWiFi connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("Signal (RSSI): ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
    digitalWrite(STATUS_LED, HIGH);
  } else {
    status.wifiConnected = false;
    Serial.println("\nFailed to connect to WiFi. Will retry...");
    digitalWrite(STATUS_LED, LOW);
  }
}

// WIFI DISCONNECTION HANDLER

void handleWiFiDisconnection() {
  status.wifiConnected = false;
  systemOnline = false;

  unsigned long currentTime = millis();
  if (currentTime - lastWifiRetryTime >= WIFI_RETRY_INTERVAL) {
    lastWifiRetryTime = currentTime;
    wifiRetryCount++;

    if (wifiRetryCount <= MAX_WIFI_RETRIES) {
      Serial.println("WiFi retry attempt: " + String(wifiRetryCount));
      initializeWiFi();
    } else {
      Serial.println("Max WiFi retries reached. Restarting system...");
      delay(5000);
      ESP.restart();
    }
  }
}


// PERFORM SENSOR READING
void performReading() {
  Serial.println("\n--- Taking Reading ---");

  float lux = lightMeter.readLightLevel();

  // Validate reading
  if (lux < 0 || isnan(lux) || isinf(lux)) {
    Serial.print("ERROR: Invalid reading - RAW LUX: ");
    Serial.println(lux);
    Serial.println("Check BH1750 connections and power supply. Skipping...");

    status.failedReadings++;
    if (status.failedReadings > 5) {
      status.sensorWorking = false;
      systemOnline = false;
      Serial.println("SENSOR MARKED AS FAILED - Too many invalid readings");
    }
    printSystemStatus();
    return;
  }

  if (lux > 100000) {
    Serial.print("ERROR: Unrealistic reading - LUX: ");
    Serial.println(lux);
    Serial.println("Sensor may be damaged or disconnected. Skipping...");
    status.failedReadings++;
    printSystemStatus();
    return;
  }

  // Valid reading
  Serial.print("Light intensity: ");
  Serial.print(lux, 2);
  Serial.println(" lux");

  status.totalReadings++;
  status.failedReadings = 0;
  status.sensorWorking = true;

  // Classify pollution level
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

  // Send to server
  if (status.wifiConnected) {
    if (sendDataToServer(lux, level, deviceId)) {
      status.lastSuccessfulSend = millis();
      status.apiWorking = true;
      systemOnline = true;
      blinkLED(2, 100);  // 2 quick blinks = success
    } else {
      status.failedReadings++;
      status.apiWorking = false;
      systemOnline = false;
      blinkLED(5, 50);   // 5 rapid blinks = error
    }
  } else {
    Serial.println("WiFi not connected - data not sent");
    status.failedReadings++;
    systemOnline = false;
  }

  printSystemStatus();
}

// =============================================================================
// SEND DATA TO SERVER
// =============================================================================
bool sendDataToServer(float lux, String level, String device_id) {

  WiFiClientSecure client;
  client.setInsecure();  // skip SSL cert verification
  HTTPClient http;
  http.addHeader("ngrok-skip-browser-warning", "true");

  Serial.print("Sending to: ");
  Serial.println(serverName);

  if (!http.begin(client, serverName)) {
    Serial.println("HTTP begin failed");
    return false;
  }

  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.addHeader("User-Agent", "ESP32-LightMonitor/1.0");
  http.setTimeout(10000);
  http.setConnectTimeout(5000);

  String postData = "device_id=" + device_id +
                    "&lux="       + String(lux, 2) +
                    "&level="     + level;

  Serial.print("POST data: ");
  Serial.println(postData);

  int httpResponseCode = http.POST(postData);

  Serial.print("HTTP Response code: ");
  Serial.println(httpResponseCode);

  bool success = false;

  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.print("Response: ");
    Serial.println(response);
    success = (httpResponseCode == 200 && response.length() > 0);
  } else {
    if (httpResponseCode == -1)  Serial.println("Error: Connection refused - check server");
    else if (httpResponseCode == -11) Serial.println("Error: Timeout - server too slow");
    else if (httpResponseCode == -3)  Serial.println("Error: DNS lookup failed");
    else {
      Serial.print("Error code: ");
      Serial.println(httpResponseCode);
    }
  }

  http.end();
  Serial.println(success ? "Result: SUCCESS" : "Result: FAILED");
  return success;
}

// =============================================================================
// STATUS LED
// =============================================================================
void updateStatusLED() {
  static unsigned long lastBlink = 0;
  static bool ledState = false;

  unsigned long blinkInterval = systemOnline ? 2000 : 500;  // Slow=online, Fast=offline

  if (millis() - lastBlink >= blinkInterval) {
    lastBlink = millis();
    ledState = !ledState;
    digitalWrite(STATUS_LED, ledState);
  }
}

// =============================================================================
// BLINK LED UTILITY
// =============================================================================
void blinkLED(int count, int delayMs) {
  for (int i = 0; i < count; i++) {
    digitalWrite(STATUS_LED, HIGH);
    delay(delayMs);
    digitalWrite(STATUS_LED, LOW);
    delay(delayMs);
  }
}

// =============================================================================
// PRINT SYSTEM STATUS
// =============================================================================
void printSystemStatus() {
  Serial.println("\n=== System Status ===");
  Serial.print("WiFi:    "); Serial.println(status.wifiConnected ? "Connected"    : "Disconnected");
  Serial.print("Sensor:  "); Serial.println(status.sensorWorking ? "Working"      : "Error");
  Serial.print("API:     "); Serial.println(status.apiWorking     ? "Working"      : "Error");
  Serial.print("System:  "); Serial.println(systemOnline          ? "Online"       : "Offline");
  Serial.print("Total readings:  "); Serial.println(status.totalReadings);
  Serial.print("Failed readings: "); Serial.println(status.failedReadings);

  if (status.totalReadings > 0) {
    float successRate = (float)(status.totalReadings - status.failedReadings)
                        / status.totalReadings * 100.0;
    Serial.print("Success rate: ");
    Serial.print(successRate, 1);
    Serial.println("%");
  }
  Serial.println("====================\n");
}
