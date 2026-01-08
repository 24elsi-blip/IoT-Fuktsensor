#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include "Adafruit_seesaw.h"

Adafruit_seesaw ss;

// ======= WiFi-inställningar =======
const char* ssid     = "DITT WIFI SSID HÄR";
const char* password = "DITTWIFI LÖSENORD HÄR";


// OBS!!!!! Anpassa till din verkliga URL, t.ex:
// "https://24abcd.ssis.nu/IoT/api.php"
// eller localhost typ (:
const char* serverUrl = "https://24abcd.ssis.nu/IoT/api.php";

// hur ofta vi skickar data (ms)
const unsigned long POST_INTERVAL_MS = 60UL * 1000UL; // 1 minut
unsigned long lastPost = 0;

void setup() {
  Serial.begin(115200);
  delay(200);

  Serial.println("Seesaw Soil Sensor + HTTP POST test");

  // Starta WiFi
  WiFi.begin(ssid, password);
  Serial.print("Ansluter till WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  Serial.print("WiFi ansluten. IP: ");
  Serial.println(WiFi.localIP());

  // Starta seesaw-sensorn
  if (!ss.begin(0x36)) {
    Serial.println("seesaw not found");
    while (1) delay(1);
  } else {
    Serial.print("startad version: ");
    Serial.println(ss.getVersion(), HEX);
  }
}

void loop() {
  // Läs sensor
  float tempC = ss.getTemp();
  uint16_t capread = ss.touchRead(0);

  Serial.print("Temperatur: ");
  Serial.print(tempC);
  Serial.println(" *C");

  Serial.print("Capacitive (fukt): ");
  Serial.println(capread);

  // Skicka till servern med jämna mellanrum
  unsigned long now = millis();
  if (now - lastPost >= POST_INTERVAL_MS) {
    lastPost = now;
    sendMoisture(capread);
  }

  delay(500);
}

void sendMoisture(uint16_t moistureValue) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi inte ansluten, hoppar över POST");
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();  // hoppar över cert-koll (enkelt för skol-/testserver)

  HTTPClient https;
  if (!https.begin(client, serverUrl)) {
    Serial.println("Kunde inte starta HTTPS-anslutning");
    return;
  }

  https.addHeader("Content-Type", "application/json");

  // Skicka fukt
  String body = "{";
  body += "\"fukt\":";
  body += String(moistureValue);
  body += "}";

  Serial.print("POST body: ");
  Serial.println(body);

  int httpCode = https.POST(body);
  Serial.print("HTTP status: ");
  Serial.println(httpCode);

  String payload = https.getString();
  Serial.print("Response body: ");
  Serial.println(payload);

  https.end();
}
