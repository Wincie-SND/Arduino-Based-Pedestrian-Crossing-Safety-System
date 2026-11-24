#include <WiFiS3.h>
#include <stdio.h>
#include <string.h>

#include "secrets.h"

namespace {
constexpr char kHeartbeatPath[] = "/api/device/heartbeat";
constexpr char kIncidentPath[] = "/api/device/incidents";
constexpr unsigned long kHeartbeatIntervalMs = 1000UL;
constexpr unsigned long kWifiRetryIntervalMs = 30000UL;
constexpr unsigned long kWifiStatusPollIntervalMs = 1000UL;
constexpr unsigned long kWifiConnectWaitMs = 100UL;
constexpr unsigned long kHttpResponseTimeoutMs = 2000UL;
constexpr unsigned long kHttpConnectionTimeoutMs = 250UL;
constexpr unsigned long kIncidentRetryIntervalMs = 5000UL;
constexpr unsigned long kSensorPrintIntervalMs = 250UL;

// Ultrasonic sensors: vehicle approach zones 1 and 2.
constexpr int kTrig1 = 9;
constexpr int kEcho1 = 10;
constexpr int kTrig2 = 7;
constexpr int kEcho2 = 8;

// IR sensors: pedestrian presence in zones 1 and 2.
constexpr int kIr1 = 2;
constexpr int kIr2 = 3;

// A small active buzzer is the default. Set true for a passive piezo buzzer.
constexpr int kBuzzerPin = 4;
constexpr bool kPassiveBuzzer = false;
constexpr unsigned int kPassiveBuzzerFrequencyHz = 2000;

constexpr float kDistanceThresholdCm = 50.0F;
// Most obstacle-detection IR modules pull OUT LOW when they detect an object.
constexpr int kIrDetectedState = LOW;

unsigned long lastHeartbeatAt = 0;
unsigned long lastWifiAttemptAt = 0;
unsigned long lastWifiStatusCheckAt = 0;
unsigned long lastIncidentAttemptAt = 0;
unsigned long lastSensorPrintAt = 0;
unsigned long incidentCounter = 0;
bool wifiConnected = false;
bool wifiAttemptStarted = false;
bool wasConnected = false;
bool previousPossibleConflict = false;
bool incidentPending = false;
bool buzzerIsOn = false;
char pendingEventId[48] = {0};
char pendingIncidentPayload[600] = {0};
WiFiClient apiClient;
const char* activeApiPath = nullptr;
unsigned long apiRequestStartedAt = 0;
char apiStatusLine[64];
size_t apiStatusLength = 0;

void printEvent(const char* eventName) {
    if (strcmp(eventName, "controller_starting") == 0) {
        Serial.println(F("[SYSTEM] CrossGuard controller started."));
    } else if (strcmp(eventName, "wifi_connecting") == 0) {
        Serial.println(F("[WI-FI] Trying to connect; sensor monitoring stays active."));
    } else if (strcmp(eventName, "wifi_disconnected") == 0 || strcmp(eventName, "wifi_connection_lost") == 0) {
        Serial.println(F("[WI-FI] Connection lost. Retrying..."));
    } else if (strcmp(eventName, "incident_recorded") == 0) {
        Serial.println(F("[INCIDENT] Report sent to CrossGuard."));
    } else if (strcmp(eventName, "incident_retry_pending") == 0) {
        Serial.println(F("[INCIDENT] Send failed. Will retry."));
    } else {
        Serial.print(F("[SYSTEM] "));
        Serial.println(eventName);
    }
}

void setBuzzer(bool alarmOn) {
    if (alarmOn == buzzerIsOn) {
        return;
    }

    if (kPassiveBuzzer) {
        if (alarmOn) {
            tone(kBuzzerPin, kPassiveBuzzerFrequencyHz);
        } else {
            noTone(kBuzzerPin);
        }
    } else {
        digitalWrite(kBuzzerPin, alarmOn ? HIGH : LOW);
    }

    buzzerIsOn = alarmOn;
}

void beginWifiConnection() {
    lastWifiAttemptAt = millis();
    wifiAttemptStarted = true;
    Serial.println(F("[WI-FI] Trying to connect; sensor monitoring stays active."));

    // WiFiS3::begin() waits for WiFi's connection timeout. Keep that wait short
    // so a missing or incompatible access point cannot stall crossing detection.
    wifiConnected = WiFi.begin(WIFI_SSID, WIFI_PASSWORD) == WL_CONNECTED;
    lastWifiStatusCheckAt = millis();
}

float getDistance(int trigPin, int echoPin) {
    digitalWrite(trigPin, LOW);
    delayMicroseconds(2);

    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin, LOW);

    const unsigned long duration = pulseIn(echoPin, HIGH, 30000UL);
    if (duration == 0) {
        return -1.0F;
    }

    return duration * 0.0343F / 2.0F;
}

bool startPostJson(const char* path, const char* payload) {
    apiClient.setConnectionTimeout(kHttpConnectionTimeoutMs);
    if (!apiClient.connect(API_HOST, API_PORT)) {
        apiClient.stop();
        Serial.print(F("[CROSSGUARD] TCP connection failed to "));
        Serial.print(API_HOST);
        Serial.print(':');
        Serial.println(API_PORT);
        return false;
    }

    apiClient.print("POST ");
    apiClient.print(path);
    apiClient.println(" HTTP/1.1");
    apiClient.print("Host: ");
    apiClient.print(API_HOST);
    apiClient.print(':');
    apiClient.println(API_PORT);
    apiClient.print("Authorization: Bearer ");
    apiClient.println(DEVICE_TOKEN);
    apiClient.println("Content-Type: application/json");
    apiClient.println("Connection: close");
    apiClient.print("Content-Length: ");
    apiClient.println(strlen(payload));
    apiClient.println();
    apiClient.print(payload);

    activeApiPath = path;
    apiRequestStartedAt = millis();
    apiStatusLength = 0;
    return true;
}

void finishApiRequest(int responseCode) {
    const bool wasIncident = activeApiPath == kIncidentPath;
    activeApiPath = nullptr;
    apiClient.stop();

    const bool accepted = responseCode == 200 || responseCode == 201;
    if (responseCode == 0) {
        Serial.println(F("[CROSSGUARD] No valid HTTP response received."));
    } else {
        Serial.print(F("[CROSSGUARD] HTTP "));
        Serial.print(responseCode);
        if (responseCode == 401) {
            Serial.println(F(" - unauthorized; check DEVICE_TOKEN in secrets.h."));
        } else {
            Serial.println(accepted ? F(" - request successful.") : F(" - request failed."));
        }
    }

    if (wasIncident) {
        if (accepted) {
            incidentPending = false;
            printEvent("incident_recorded");
        } else {
            printEvent("incident_retry_pending");
        }
    }
}

void pollApiResponse() {
    if (!wifiConnected) {
        finishApiRequest(0);
        return;
    }

    // Read only the bytes already available, then return to the sensor loop.
    int availableBytes = apiClient.available();
    while (availableBytes-- > 0 && apiStatusLength < sizeof(apiStatusLine) - 1) {
        const int nextByte = apiClient.read();
        if (nextByte < 0) {
            break;
        }
        if (nextByte == '\n') {
            apiStatusLine[apiStatusLength] = '\0';
            int responseCode = 0;
            sscanf(apiStatusLine, "HTTP/%*s %d", &responseCode);
            finishApiRequest(responseCode);
            return;
        }
        apiStatusLine[apiStatusLength++] = static_cast<char>(nextByte);
    }

    if (apiStatusLength == sizeof(apiStatusLine) - 1 || millis() - apiRequestStartedAt >= kHttpResponseTimeoutMs) {
        finishApiRequest(0);
    }
}

void startHeartbeat(float distance1, float distance2) {
    const bool ultrasonicReady = distance1 >= 0.0F && distance2 >= 0.0F;
    char payload[180];
    snprintf(
        payload,
        sizeof(payload),
        "{\"status\":\"online\",\"components\":{\"ultrasonic\":\"%s\",\"infrared\":\"online\",\"buzzer\":\"online\"}}",
        ultrasonicReady ? "online" : "warning"
    );

    startPostJson(kHeartbeatPath, payload);
}

void printDistanceAndVehicle(float distance, bool vehicleDetected) {
    if (distance < 0.0F) {
        Serial.print(F("no echo"));
    } else {
        Serial.print(distance, 2);
        Serial.print(F(" cm"));
    }

    Serial.print(F(" / vehicle "));
    Serial.print(vehicleDetected ? F("YES") : F("no"));
}

void printSensorStatus(
    float distance1,
    float distance2,
    bool pedestrian1,
    bool pedestrian2,
    bool vehicle1,
    bool vehicle2,
    bool pedestrianDetected,
    bool vehicleDetected,
    bool conflict1,
    bool conflict2,
    bool possibleConflict,
    bool buzzerActive
) {
    Serial.print(F("US1: "));
    printDistanceAndVehicle(distance1, vehicle1);
    Serial.print(F(" | US2: "));
    printDistanceAndVehicle(distance2, vehicle2);
    Serial.print(F(" | IR1: "));
    Serial.print(pedestrian1 ? F("pedestrian") : F("clear"));
    Serial.print(F(" | IR2: "));
    Serial.print(pedestrian2 ? F("pedestrian") : F("clear"));
    Serial.print(F(" | Ped: "));
    Serial.print(pedestrianDetected ? F("YES") : F("no"));
    Serial.print(F(" | Vehicle: "));
    Serial.print(vehicleDetected ? F("YES") : F("no"));
    Serial.print(F(" | Z1 conflict: "));
    Serial.print(conflict1 ? F("YES") : F("no"));
    Serial.print(F(" | Z2 conflict: "));
    Serial.print(conflict2 ? F("YES") : F("no"));
    Serial.print(F(" | ALERT: "));
    Serial.print(possibleConflict ? F("YES") : F("no"));
    Serial.print(F(" | Buzzer: "));
    Serial.println(buzzerActive ? F("ON") : F("off"));
}

void queueIncidentIfNeeded(
    bool possibleConflict,
    bool pedestrian1,
    bool pedestrian2,
    bool vehicle1,
    bool vehicle2
) {
    if (possibleConflict && !previousPossibleConflict && !incidentPending) {
        ++incidentCounter;
        snprintf(
            pendingEventId,
            sizeof(pendingEventId),
            "crossing-%lu-%lu",
            millis(),
            incidentCounter
        );
        const char* pedestrianStatus = pedestrian1 && pedestrian2
            ? "Pedestrian detected in zones 1 and 2"
            : (pedestrian1 ? "Pedestrian detected in zone 1" : "Pedestrian detected in zone 2");
        const char* vehicleStatus = vehicle1 && vehicle2
            ? "Vehicle detected in zones 1 and 2"
            : (vehicle1 ? "Vehicle detected in zone 1" : "Vehicle detected in zone 2");
        snprintf(
            pendingIncidentPayload,
            sizeof(pendingIncidentPayload),
            "{\"event_id\":\"%s\",\"incident_type\":\"Potential crossing conflict\","
            "\"detection_method\":\"2 ultrasonic + 2 IR sensors\","
            "\"pedestrian_status\":\"%s\",\"vehicle_status\":\"%s\","
            "\"event_description\":\"IR sensors detected a pedestrian while ultrasonic sensors detected a vehicle.\"}",
            pendingEventId,
            pedestrianStatus,
            vehicleStatus
        );
        incidentPending = true;
        // Try to send the new event on this loop pass.
        lastIncidentAttemptAt = millis() - kIncidentRetryIntervalMs;
    }

    previousPossibleConflict = possibleConflict;
}

}  // namespace

void setup() {
    Serial.begin(115200);
    const unsigned long serialWaitStartedAt = millis();
    while (!Serial && millis() - serialWaitStartedAt < 2500UL) {
        delay(10);
    }

    pinMode(kTrig1, OUTPUT);
    pinMode(kEcho1, INPUT);
    pinMode(kTrig2, OUTPUT);
    pinMode(kEcho2, INPUT);
    pinMode(kIr1, INPUT);
    pinMode(kIr2, INPUT);
    pinMode(kBuzzerPin, OUTPUT);

    digitalWrite(kTrig1, LOW);
    digitalWrite(kTrig2, LOW);
    digitalWrite(kBuzzerPin, LOW);

    WiFi.setTimeout(kWifiConnectWaitMs);
    printEvent("controller_starting");
    lastWifiStatusCheckAt = millis();
    lastHeartbeatAt = millis() - kHeartbeatIntervalMs;
}

void loop() {
    // Keep the two ultrasonic pings apart to reduce cross-talk.
    const float distance1 = getDistance(kTrig1, kEcho1);
    delay(60);
    const float distance2 = getDistance(kTrig2, kEcho2);

    const bool pedestrian1 = digitalRead(kIr1) == kIrDetectedState;
    const bool pedestrian2 = digitalRead(kIr2) == kIrDetectedState;
    const bool vehicle1 = distance1 > 0.0F && distance1 <= kDistanceThresholdCm;
    const bool vehicle2 = distance2 > 0.0F && distance2 <= kDistanceThresholdCm;
    const bool pedestrianDetected = pedestrian1 || pedestrian2;
    const bool vehicleDetected = vehicle1 || vehicle2;
    const bool conflict1 = pedestrian1 && vehicle1;
    const bool conflict2 = pedestrian2 && vehicle2;
    // A single sensor group alone is not a conflict: a person and a vehicle
    // must both be detected before the alarm or incident is activated.
    const bool possibleConflict = pedestrianDetected && vehicleDetected;
    setBuzzer(possibleConflict);

    const unsigned long now = millis();
    if (now - lastSensorPrintAt >= kSensorPrintIntervalMs) {
        printSensorStatus(
            distance1,
            distance2,
            pedestrian1,
            pedestrian2,
            vehicle1,
            vehicle2,
            pedestrianDetected,
            vehicleDetected,
            conflict1,
            conflict2,
            possibleConflict,
            possibleConflict
        );
        lastSensorPrintAt = now;
    }
    queueIncidentIfNeeded(possibleConflict, pedestrian1, pedestrian2, vehicle1, vehicle2);

    if (now - lastWifiStatusCheckAt >= kWifiStatusPollIntervalMs) {
        lastWifiStatusCheckAt = now;
        wifiConnected = WiFi.status() == WL_CONNECTED;
    }

    if (wifiConnected && !wasConnected) {
        Serial.print(F("[WI-FI] Connected. Arduino IP: "));
        Serial.print(WiFi.localIP());
        Serial.println();
        Serial.print(F("[WI-FI] Connectivity firmware: "));
        Serial.println(WiFi.firmwareVersion());
    } else if (!wifiConnected && wasConnected) {
        printEvent("wifi_connection_lost");
    }
    wasConnected = wifiConnected;

    const bool shouldTryWifi = !wifiAttemptStarted
        || (!wifiConnected && now - lastWifiAttemptAt >= kWifiRetryIntervalMs);
    if (shouldTryWifi) {
        beginWifiConnection();
    }

    if (activeApiPath != nullptr) {
        pollApiResponse();
    } else if (wifiConnected && incidentPending && now - lastIncidentAttemptAt >= kIncidentRetryIntervalMs) {
        lastIncidentAttemptAt = now;
        if (!startPostJson(kIncidentPath, pendingIncidentPayload)) {
            printEvent("incident_retry_pending");
        }
    } else if (wifiConnected && millis() - lastHeartbeatAt >= kHeartbeatIntervalMs) {
        lastHeartbeatAt = millis();
        startHeartbeat(distance1, distance2);
    }
}
