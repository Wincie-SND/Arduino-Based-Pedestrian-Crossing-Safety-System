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
constexpr unsigned long kPedestrianAlertDurationMs = 4000UL;

// A small active buzzer is the default. Set true for a passive piezo buzzer.
constexpr int kBuzzerPin = 4;
constexpr bool kPassiveBuzzer = false;
constexpr unsigned int kPassiveBuzzerFrequencyHz = 2000;
constexpr unsigned long kBuzzerBeepIntervalMs = 250UL;

constexpr unsigned long kWarningLedSlowBlinkIntervalMs = 500UL;
constexpr unsigned long kWarningLedFastBlinkIntervalMs = 200UL;

constexpr float kUltrasonic1RangeCm = 18.0F;
constexpr float kUltrasonic2RangeCm = 20.0F;
// Most obstacle-detection IR modules pull OUT LOW when they detect an object.
constexpr int kIrDetectedState = LOW;

struct PedestrianAlert {
    bool previousDetection = false;
    bool active = false;
    unsigned long startedAt = 0;

    bool update(bool detected, unsigned long now) {
        const bool newDetection = detected && !previousDetection;
        previousDetection = detected;
        if (newDetection) {
            startedAt = now;
            active = true;
        }

        active = active && now - startedAt < kPedestrianAlertDurationMs;
        return newDetection;
    }
};

struct CrossingGroup {
    int number;
    int irPin;
    int trigPin;
    int echoPin;
    int ledPin;
    PedestrianAlert ledAlert;
    unsigned long ledBlinkIntervalMs = kWarningLedSlowBlinkIntervalMs;
    unsigned long ledModeStartedAt = 0;
    bool previousConflict = false;
    bool incidentPending = false;
    unsigned long lastIncidentAttemptAt = 0;
    char pendingEventId[48] = {};
};

// Group number, IR, ultrasonic TRIG, ultrasonic ECHO, and LED driver.
CrossingGroup group1{1, 2, 9, 10, 5};
CrossingGroup group2{2, 3, 7, 8, 6};

unsigned long lastHeartbeatAt = 0;
unsigned long lastWifiAttemptAt = 0;
unsigned long lastWifiStatusCheckAt = 0;
unsigned long lastSensorPrintAt = 0;
unsigned long incidentCounter = 0;
bool wifiConnected = false;
bool wifiAttemptStarted = false;
bool wasConnected = false;
bool buzzerIsOn = false;
PedestrianAlert buzzerAlert;
WiFiClient apiClient;
bool apiRequestActive = false;
CrossingGroup* activeIncidentGroup = nullptr;
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

void updateBuzzerAlert(bool pedestrianDetected, unsigned long now) {
    buzzerAlert.update(pedestrianDetected, now);
    const unsigned long elapsed = now - buzzerAlert.startedAt;
    setBuzzer(buzzerAlert.active && (elapsed / kBuzzerBeepIntervalMs) % 2UL == 0);
}

void initializeGroupPins(const CrossingGroup& group) {
    pinMode(group.irPin, INPUT);
    pinMode(group.trigPin, OUTPUT);
    pinMode(group.echoPin, INPUT);
    digitalWrite(group.trigPin, LOW);
    pinMode(group.ledPin, OUTPUT);
    digitalWrite(group.ledPin, LOW);
}

void updateWarningLeds(CrossingGroup& group, bool pedestrianDetected, bool conflict, unsigned long now) {
    const bool alertStarted = group.ledAlert.update(pedestrianDetected, now);
    const unsigned long blinkIntervalMs = conflict ? kWarningLedFastBlinkIntervalMs : kWarningLedSlowBlinkIntervalMs;
    if (alertStarted || blinkIntervalMs != group.ledBlinkIntervalMs) {
        group.ledBlinkIntervalMs = blinkIntervalMs;
        group.ledModeStartedAt = now;
    }

    const bool ledOn = group.ledAlert.active
        && ((now - group.ledModeStartedAt) / blinkIntervalMs) % 2UL == 0;
    digitalWrite(group.ledPin, ledOn ? HIGH : LOW);
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

bool startPostJson(const char* path, const char* payload, CrossingGroup* incidentGroup) {
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

    apiRequestActive = true;
    activeIncidentGroup = incidentGroup;
    apiRequestStartedAt = millis();
    apiStatusLength = 0;
    return true;
}

void finishApiRequest(int responseCode) {
    CrossingGroup* const incidentGroup = activeIncidentGroup;
    apiRequestActive = false;
    activeIncidentGroup = nullptr;
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

    if (incidentGroup != nullptr) {
        if (accepted) {
            incidentGroup->incidentPending = false;
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

void startHeartbeat() {
    // Echo availability depends on the scene. The heartbeat reports controller availability.
    startPostJson(
        kHeartbeatPath,
        "{\"status\":\"online\",\"components\":{\"ultrasonic\":\"online\","
        "\"infrared\":\"online\",\"buzzer\":\"online\",\"leds\":\"online\"}}",
        nullptr
    );
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
    bool conflict1,
    bool conflict2,
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
    Serial.print((pedestrian1 || pedestrian2) ? F("YES") : F("no"));
    Serial.print(F(" | Vehicle: "));
    Serial.print((vehicle1 || vehicle2) ? F("YES") : F("no"));
    Serial.print(F(" | Group 1 violation: "));
    Serial.print(conflict1 ? F("YES") : F("no"));
    Serial.print(F(" | Group 2 violation: "));
    Serial.print(conflict2 ? F("YES") : F("no"));
    Serial.print(F(" | ALERT: "));
    Serial.print((conflict1 || conflict2) ? F("YES") : F("no"));
    Serial.print(F(" | Buzzer: "));
    Serial.println(buzzerActive ? F("ON") : F("off"));
}

void queueIncidentIfNeeded(CrossingGroup& group, bool conflict, unsigned long now) {
    if (conflict && !group.previousConflict && !group.incidentPending) {
        snprintf(
            group.pendingEventId,
            sizeof(group.pendingEventId),
            "crossing-%lu-%lu",
            now,
            ++incidentCounter
        );
        group.incidentPending = true;
        group.lastIncidentAttemptAt = now - kIncidentRetryIntervalMs;
    }

    group.previousConflict = conflict;
}

bool tryStartIncident(CrossingGroup& group, unsigned long now) {
    if (!group.incidentPending || now - group.lastIncidentAttemptAt < kIncidentRetryIntervalMs) {
        return false;
    }

    group.lastIncidentAttemptAt = now;
    char payload[400];
    snprintf(
        payload,
        sizeof(payload),
        "{\"event_id\":\"%s\",\"incident_type\":\"Potential crossing conflict\","
        "\"detection_method\":\"IR and ultrasonic sensors\","
        "\"pedestrian_status\":\"Pedestrian detected in group %d\","
        "\"vehicle_status\":\"Vehicle detected in group %d\","
        "\"event_description\":\"Group %d detected a pedestrian and a vehicle at the same time.\"}",
        group.pendingEventId,
        group.number,
        group.number,
        group.number
    );
    if (!startPostJson(kIncidentPath, payload, &group)) {
        printEvent("incident_retry_pending");
    }
    return true;
}

}  // namespace

void setup() {
    Serial.begin(115200);
    const unsigned long serialWaitStartedAt = millis();
    while (!Serial && millis() - serialWaitStartedAt < 2500UL) {
        delay(10);
    }

    initializeGroupPins(group1);
    initializeGroupPins(group2);
    pinMode(kBuzzerPin, OUTPUT);
    digitalWrite(kBuzzerPin, LOW);

    WiFi.setTimeout(kWifiConnectWaitMs);
    printEvent("controller_starting");
    lastWifiStatusCheckAt = millis();
    lastHeartbeatAt = millis() - kHeartbeatIntervalMs;
}

void loop() {
    const float distance1 = getDistance(group1.trigPin, group1.echoPin);
    const bool pedestrian1 = digitalRead(group1.irPin) == kIrDetectedState;
    // Keep the two ultrasonic pings apart to reduce cross-talk.
    delay(60);
    const float distance2 = getDistance(group2.trigPin, group2.echoPin);
    const bool pedestrian2 = digitalRead(group2.irPin) == kIrDetectedState;

    const bool vehicle1 = distance1 > 0.0F && distance1 <= kUltrasonic1RangeCm;
    const bool vehicle2 = distance2 > 0.0F && distance2 <= kUltrasonic2RangeCm;
    const bool conflict1 = pedestrian1 && vehicle1;
    const bool conflict2 = pedestrian2 && vehicle2;
    const unsigned long now = millis();
    updateBuzzerAlert(pedestrian1 || pedestrian2, now);
    updateWarningLeds(group1, pedestrian1, conflict1, now);
    updateWarningLeds(group2, pedestrian2, conflict2, now);

    if (now - lastSensorPrintAt >= kSensorPrintIntervalMs) {
        printSensorStatus(
            distance1,
            distance2,
            pedestrian1,
            pedestrian2,
            vehicle1,
            vehicle2,
            conflict1,
            conflict2,
            buzzerIsOn
        );
        lastSensorPrintAt = now;
    }
    queueIncidentIfNeeded(group1, conflict1, now);
    queueIncidentIfNeeded(group2, conflict2, now);

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

    if (apiRequestActive) {
        pollApiResponse();
        return;
    }

    if (!wifiConnected || tryStartIncident(group1, now) || tryStartIncident(group2, now)) {
        return;
    }

    if (millis() - lastHeartbeatAt >= kHeartbeatIntervalMs) {
        lastHeartbeatAt = millis();
        startHeartbeat();
    }
}
