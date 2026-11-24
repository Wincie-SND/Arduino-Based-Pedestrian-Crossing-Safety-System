# CrossGuard Arduino sensor controller

The sketch reads two ultrasonic distance sensors and two digital IR obstacle sensors. The IR sensors indicate pedestrian presence and the ultrasonic sensors indicate vehicle presence. It sounds the buzzer and posts an incident only when at least one IR sensor and at least one ultrasonic sensor detect something at the same time. Either sensor group detecting alone does not trigger an alarm. It also prints JSON snapshots to Serial and keeps the CrossGuard device heartbeat alive.

## Pin connections

Use the Arduino's **5V** and **GND** rails. All sensor modules, the buzzer, and the Arduino must share a common ground.

| Module | Module pin | Arduino UNO pin |
| --- | --- | --- |
| Ultrasonic sensor 1 (zone 1) | TRIG | D9 |
| Ultrasonic sensor 1 (zone 1) | ECHO | D10 |
| Ultrasonic sensor 2 (zone 2) | TRIG | D7 |
| Ultrasonic sensor 2 (zone 2) | ECHO | D8 |
| IR sensor 1 (zone 1) | OUT / DO | D2 |
| IR sensor 2 (zone 2) | OUT / DO | D3 |
| Small two-pin active buzzer or passive piezo | + | D4 |
| Small two-pin active buzzer or passive piezo | − | GND |
| Three-pin buzzer module, if used | SIG / IN | D4 |
| Three-pin buzzer module, if used | VCC and GND | 5V and GND, as marked on the module |
| Both ultrasonic sensors | VCC | 5V |
| Both IR sensors | VCC | 5V, if supported by the sensor module |
| All sensors and two-pin buzzer | GND | GND |

```text
                     Arduino UNO
                  ┌─────────────────┐
  Ultrasonic 1    │                 │
    TRIG ─────────┤ D9              │
    ECHO ─────────┤ D10             │
  Ultrasonic 2    │                 │
    TRIG ─────────┤ D7              │
    ECHO ─────────┤ D8              │
  IR sensor 1     │                 │
    OUT  ─────────┤ D2              │
  IR sensor 2     │                 │
    OUT  ─────────┤ D3              │
  Buzzer (+/SIG) ─┤ D4              │
  All sensor VCC ─┤ 5V              │
  All grounds ────┤ GND             │
                  └─────────────────┘
```

Connect each sensor's **VCC** and **GND** to the matching shared rails; the drawing shows those shared connections once for clarity. Connect a two-pin buzzer's positive lead to D4 and negative lead to GND. For a three-pin buzzer module, connect SIG/IN to D4 and its power pins as labeled. Use a small, low-current buzzer; use a transistor driver if the buzzer needs more current than an Arduino output can provide. Use the UNO's standard labeled headers, not the UNO R4 WiFi ESP32 header. Point each ultrasonic sensor toward approaching traffic in its zone. Position each IR sensor to cover pedestrian presence in the matching zone. Adjust the IR module's onboard potentiometer if its detection range needs tuning.

The buzzer sounds continuously while both `pedestrian_detected` and `vehicle_detected` are true, and turns off if either group clears. `possible_conflict` is exactly their AND. `conflict_1` and `conflict_2` indicate a person and vehicle detected in that matching zone; the overall alarm can also activate when a pedestrian and vehicle are detected in different zones. The sketch defaults to an **active buzzer**. For a passive piezo, set `kPassiveBuzzer` to `true`; it will use a 2 kHz tone. The Serial snapshot includes `buzzer_active` so the laptop can see when the alarm output is on.

This pinout matches the sketch. The default IR setting treats **LOW** on OUT as detection. If your module outputs HIGH when it detects an object, change `kIrDetectedState` from `LOW` to `HIGH` near the top of the sketch. Both ultrasonic sensors use a vehicle detection cutoff of `50 cm` (`kDistanceThresholdCm`). The IR modules report presence as a digital signal. If your IR modules support 50 cm, place a target 50 cm from each module and adjust its onboard potentiometer until its detection indicator just turns on.

## UNO model support

The complete sketch targets **Arduino UNO R4 WiFi** and uses its `WiFiS3` library to contact the CrossGuard API. An **UNO R3** can use the same sensor pins and detection logic, and can print the JSON over USB Serial, but it cannot compile/run this Wi-Fi version unchanged: it has no built-in Wi-Fi and does not provide `WiFiS3`. Using the API from an R3 requires adding a compatible Wi-Fi/Ethernet module and adapting the networking code.

## Set up the CrossGuard API connection

1. Copy `secrets.example.h` to `secrets.h` in this folder.
2. Set the Wi-Fi name and password, the laptop's Wi-Fi IPv4 address in `API_HOST`, and the device token in `DEVICE_TOKEN`.
3. Start MySQL, then run `php artisan migrate` in the Laravel project folder.
4. Issue a token for the registered Arduino device with `php artisan crossguard:device-token "Arduino control board"`. Save the token shown and put it in `DEVICE_TOKEN`.
5. In one terminal, start the web app for the laptop browser with `php artisan serve --host=127.0.0.1 --port=8000`. Keep opening the app as `http://localhost:8000` so the browser can use the laptop webcam.
6. In a second terminal, start the device API on the laptop's current Wi-Fi IPv4 address, for example `php artisan serve --host=192.168.254.187 --port=8001`. Replace the example address with the laptop's current Wi-Fi IPv4 address. If Windows Firewall asks, allow this port on the private network.
7. Set `API_HOST` to that same laptop Wi-Fi IPv4 address and `API_PORT` to `8001` in `secrets.h`.
8. Open `CrossGuardController.ino` in Arduino IDE, select **Arduino UNO R4 WiFi**, and upload it.
9. Open Serial Monitor at **115200 baud**. It prints one compact sensor line each loop and a short HTTP status line for heartbeat and incident requests.

The sensor line includes ultrasonic distance, IR presence, vehicle/pedestrian detection, zone conflicts, alarm state, and buzzer state. Heartbeats run every second and refresh controller, network, ultrasonic, IR, and buzzer status in the web app. The Wi-Fi connection attempt waits no longer than 100 ms in the sensor loop and retries every 30 seconds, so an unavailable access point does not hold detection for its full connection timeout. The ultrasonic status is a warning if either sensor has no echo. IR and buzzer statuses mean the controller can read or drive their pins; this wiring has no feedback signal to prove a disconnected IR module or silent buzzer. The laptop webcam and LED warning lights are not monitored by the Arduino sketch. Incidents are submitted once when a conflict changes from clear to detected and retried if the network/API request fails.

The buzzer is driven before serial output or network requests. Sensor reads keep the ultrasonic cross-talk spacing but no longer pause 300 ms at the end of each loop. HTTP connection and response waits are capped so a slow/unavailable server does not stall detection for seconds.

Keep the web app on `http://localhost:8000` in the laptop browser. The Arduino sends API requests to the laptop's Wi-Fi IPv4 address on port `8001`; `localhost` from the board would refer to the board itself.

## Example layout

```text
              Vehicle approach zone 1           Vehicle approach zone 2
                        ↓                                  ↓
          [Ultrasonic 1: D9/D10]             [Ultrasonic 2: D7/D8]
                        ↓                                  ↓
          [Pedestrian IR 1: D2]               [Pedestrian IR 2: D3]
```
