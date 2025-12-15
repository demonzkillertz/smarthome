#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

const char* ssid = "Khwopa";
const char* password = "khwop@123";
const char* serverUrl = "https://ksc.khec.edu.np/smart/control.php?action=get_pins";

// We need a secure client for HTTPS
WiFiClientSecure client;

void setup() {
  Serial.begin(115200);
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnected to WiFi");

  // Ignore SSL certificate validation for simplicity
  client.setInsecure();

  // Initial configuration of pins
  updatePins();
}

void loop() {
  updatePins();
  delay(1000); // Poll every 1 second for fast reaction
}

void updatePins() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    
    // Use the secure client
    http.begin(client, serverUrl);
    http.setUserAgent("ESP32-SmartHome/1.0"); // Add User-Agent to avoid being blocked
    
    int httpCode = http.GET();
    
    if (httpCode > 0) {
      String payload = http.getString();
      
      // Parse JSON
      JsonDocument doc; 
      DeserializationError error = deserializeJson(doc, payload);

      if (!error) {
        JsonArray array = doc.as<JsonArray>();
        
        for(JsonVariant v : array) {
            int pin = v["pin_number"];
            int status = v["status"];
            
            // Debug print to confirm ESP is receiving the command
            Serial.print("GPIO ");
            Serial.print(pin);
            Serial.print(" -> ");
            Serial.println(status ? "HIGH (ON)" : "LOW (OFF)");

            pinMode(pin, OUTPUT); 
            
            // If your relay is "Active LOW" (ON when LOW), change this line to:
            // digitalWrite(pin, status ? LOW : HIGH);
            digitalWrite(pin, status ? HIGH : LOW);
        }
      } else {
        Serial.print("deserializeJson() failed: ");
        Serial.println(error.c_str());
        Serial.println("Payload received:");
        Serial.println(payload); // Print the actual response to see why it failed
      }
    } else {
      Serial.printf("HTTP GET failed, error: %s\n", http.errorToString(httpCode).c_str());
    }
    
    http.end();
  } else {
    Serial.println("WiFi Disconnected");
  }
}