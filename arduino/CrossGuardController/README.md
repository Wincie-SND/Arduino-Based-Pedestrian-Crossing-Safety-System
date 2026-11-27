# CrossGuard Arduino sensor controller

Each crossing group has an IR sensor for pedestrians, an ultrasonic sensor for vehicles, and three warning LEDs that share one control pin per group. The sketch reports a possible violation to CrossGuard when both sensors in the same group detect a pedestrian and a vehicle. It also prints sensor readings to Serial and sends device heartbeats.

## Pin connections

Use the Arduino's **5V** and **GND** rails. The sensor modules, buzzer, LEDs, and Arduino share a common ground.

| Group | Component | Arduino UNO signal pins |
| --- | --- | --- |
| 1 | IR sensor 1 | OUT / DO → D2 |
| 1 | Ultrasonic sensor 1 | TRIG → D9, ECHO → D10 |
| 1 | Three warning LEDs | D5, active high |
| 2 | IR sensor 2 | OUT / DO → D3 |
| 2 | Ultrasonic sensor 2 | TRIG → D7, ECHO → D8 |
| 2 | Three warning LEDs | D6, active high |
| Shared | Active buzzer or passive piezo | Positive lead or SIG / IN → D4 |

Connect each sensor's GND to the shared ground and each ultrasonic sensor's VCC to 5V. Connect the IR sensors to 5V if their modules support it. Connect a two-pin buzzer's negative lead to GND. For a three-pin buzzer module, connect its power pins as labeled. Use a small, low-current buzzer or a transistor driver if the buzzer needs more current than an Arduino output can provide. Use the UNO's standard labeled headers. Point each ultrasonic sensor toward approaching traffic in its group, and position the corresponding IR sensor to cover pedestrian presence.

### LED wiring for each group

For three small indicator LEDs wired directly, use one branch per LED. Connect D5 or D6 to the LED's positive leg through its own **2.2 kΩ or 10 kΩ resistor**, then connect the negative leg to GND. The 10 kΩ option gives lower brightness. The UNO R4 WiFi GPIO limit is 8 mA per pin. [Arduino UNO R4 WiFi datasheet](https://docs.arduino.cc/resources/datasheets/ABX00087-datasheet.pdf)

For a MOSFET circuit, use one logic-level N-channel MOSFET per group. The 5V rail supplies the LEDs, and each MOSFET carries the current for its three LEDs.

1. Connect the group's control pin to its MOSFET gate through a **1 kΩ resistor**. Connect a **10 kΩ resistor** from that gate to GND to keep the LEDs off at startup.
2. Connect the MOSFET source to Arduino GND and the three negative LED legs from that group to its MOSFET drain.
3. Connect each LED's positive leg to **5V** through its own **470 Ω resistor**.

When either IR sensor first detects a pedestrian, the **D4 buzzer** starts a **4-second** crossing alert with 250 ms on and 250 ms off. The crossing alert runs for four seconds even if the pedestrian clears the sensors early. Both IR sensors must clear before a new pedestrian detection can start another crossing alert. A pedestrian and vehicle detected in the same group override the crossing cadence with 125 ms on and 125 ms off for as long as a conflict is detected. When all conflicts clear, the buzzer returns to the crossing cadence if time remains in the crossing alert. The sketch defaults to an **active buzzer**. For a passive piezo, set `kPassiveBuzzer` to `true` to use a 2 kHz tone.

Each group's three LEDs blink together for **4 seconds** when that group's IR sensor first detects a pedestrian. They blink quickly during a same-group violation (200 ms on, 200 ms off) and slowly during the rest of the alert (500 ms on, 500 ms off). The LEDs finish the four-second pattern even if the pedestrian clears the sensor early. That group's IR sensor must clear before a new detection can start another pattern. If IR1 detects a pedestrian and only ultrasonic sensor 2 detects a vehicle, Group 1 blinks slowly and the violation status stays clear.

Ultrasonic sensor 1 counts a vehicle at a positive distance up to **18 cm**, and ultrasonic sensor 2 counts a vehicle at a positive distance up to **20 cm**. Each group applies its own cutoff.

The default IR setting treats **LOW** on OUT as detection. If your module outputs HIGH when it detects an object, change `kIrDetectedState` from `LOW` to `HIGH` near the top of the sketch. The IR modules report presence as a digital signal. If your IR modules support 50 cm, place a target 50 cm from each module and adjust its onboard potentiometer until its detection indicator just turns on.

## UNO model support

The complete sketch targets **Arduino UNO R4 WiFi** and uses its `WiFiS3` library to contact the CrossGuard API. An **UNO R3** can use the same sensor pins and detection logic and print sensor readings over USB Serial. Using the API from an R3 requires adding a compatible Wi-Fi/Ethernet module and adapting the networking code.

## Set up the CrossGuard API connection

1. Copy `secrets.example.h` to `secrets.h` in this folder.
2. Set the Wi-Fi name and password, the laptop's Wi-Fi IPv4 address in `API_HOST`, and the device token in `DEVICE_TOKEN`.
3. Start MySQL, then run `php artisan migrate` in the Laravel project folder.
4. Issue a token for the registered Arduino device with `php artisan crossguard:device-token "Arduino control board"`. Save the token shown and put it in `DEVICE_TOKEN`.
5. In one terminal, start the web app for the laptop browser with `php artisan serve --host=127.0.0.1 --port=8000`. Keep opening the app as `http://localhost:8000` so the browser can use the laptop webcam.
6. In a second terminal, start the device API on the laptop's current Wi-Fi IPv4 address, for example `php artisan serve --host=192.168.254.187 --port=8001`. Replace the example address with the laptop's current Wi-Fi IPv4 address. If Windows Firewall asks, allow this port on the private network.
7. Set `API_HOST` to that same laptop Wi-Fi IPv4 address and `API_PORT` to `8001` in `secrets.h`.
8. Open `CrossGuardController.ino` in Arduino IDE, select **Arduino UNO R4 WiFi**, and upload it.
9. Open Serial Monitor at **115200 baud**. It prints a compact sensor line at most four times per second and a short HTTP status line for heartbeat and incident requests.

The sensor line includes ultrasonic distance, IR presence, and each group's violation status. `ALERT` shows whether either group detects a violation, and `Buzzer` shows whether the output is currently sounding. Heartbeats run every second and refresh controller, network, ultrasonic, IR, buzzer, and LED status in the web app. Those statuses reflect the controller's availability. To verify echo readings, hold a flat target about 10 cm in front of each ultrasonic sensor and check the `US1` and `US2` readings in Serial Monitor. An empty detection area can produce `no echo`.

The Wi-Fi connection attempt waits no longer than 100 ms in the sensor loop and retries every 30 seconds, so an unavailable access point does not hold detection for its full connection timeout. Each group queues a report when its own violation starts and retries failed requests independently. The controller holds one pending report per group.

The buzzer and LEDs use timers so the sketch keeps reading sensors and handling network requests during the alert. The sketch updates the outputs before serial output and network requests. Sensor reads keep the ultrasonic cross-talk spacing. HTTP connection and response waits are capped.

Keep the web app on `http://localhost:8000` in the laptop browser. The Arduino sends API requests to the laptop's Wi-Fi IPv4 address on port `8001`; `localhost` from the board would refer to the board itself.

## Example layout

```text
               Vehicle approach group 1          Vehicle approach group 2
                        ↓                                  ↓
          [Ultrasonic 1: D9/D10]             [Ultrasonic 2: D7/D8]
                        ↓                                  ↓
          [Pedestrian IR 1: D2]               [Pedestrian IR 2: D3]
          [Three LEDs: D5]                   [Three LEDs: D6]
```
