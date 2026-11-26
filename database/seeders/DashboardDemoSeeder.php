<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\CrossingDevice;
use App\Models\CrossingIncident;
use App\Models\CrossingIncidentEvent;
use App\Models\CrossingIssue;
use App\Models\CrossingNotification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Seed local-only users and sample dashboard records.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function (): void {
            $team = Team::firstOrCreate(
                ['slug' => 'lnu-youngfield-crossing'],
                [
                    'name' => 'LNU Youngfield Pedestrian Crossing',
                    'is_personal' => false,
                ],
            );

            $accounts = [
                [
                    'name' => 'CrossGuard Administrator',
                    'email' => 'admin@crossguard.test',
                    'password' => 'CrossGuardAdmin123!',
                    'role' => TeamRole::Admin,
                ],
                [
                    'name' => 'Crossing Officer',
                    'email' => 'officer@crossguard.test',
                    'password' => 'CrossGuardOfficer123!',
                    'role' => TeamRole::Enforcer,
                ],
            ];

            $users = [];

            foreach ($accounts as $account) {
                $user = User::updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'email_verified_at' => now(),
                        'password' => Hash::make($account['password']),
                        'current_team_id' => $team->id,
                    ],
                );

                $team->memberships()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role' => $account['role']->value],
                );

                $users[$account['email']] = $user;
            }

            $now = now();

            foreach ([
                ['name' => 'Arduino control board', 'category' => 'controller', 'status' => 'online'],
                ['name' => 'Ultrasonic sensor', 'category' => 'sensor', 'status' => 'online'],
                ['name' => 'Infrared sensor', 'category' => 'sensor', 'status' => 'online'],
                ['name' => 'Camera module', 'category' => 'camera', 'status' => 'online'],
                ['name' => 'LED warning lights', 'category' => 'warning', 'status' => 'warning'],
                ['name' => 'Buzzer', 'category' => 'warning', 'status' => 'online'],
                ['name' => 'Network connection', 'category' => 'network', 'status' => 'warning'],
            ] as $device) {
                CrossingDevice::updateOrCreate(
                    ['team_id' => $team->id, 'name' => $device['name']],
                    [
                        'category' => $device['category'],
                        'status' => $device['status'],
                        'last_seen_at' => $now->copy()->subMinutes(2),
                    ],
                );
            }

            foreach ([
                ['code' => 'CG-2026-001', 'type' => 'Vehicle in crosswalk', 'status' => 'needs_review', 'when' => $now->copy()->subMinutes(26)],
                ['code' => 'CG-2026-002', 'type' => 'Vehicle failed to yield', 'status' => 'verified', 'when' => $now->copy()->subHours(3)],
                ['code' => 'CG-2026-003', 'type' => 'Pedestrian signal obstruction', 'status' => 'resolved', 'when' => $now->copy()->subDay()],
            ] as $incident) {
                $record = CrossingIncident::updateOrCreate(
                    ['team_id' => $team->id, 'incident_code' => $incident['code']],
                    [
                        'incident_type' => $incident['type'],
                        'status' => $incident['status'],
                        'occurred_at' => $incident['when'],
                        'evidence_path' => null,
                        'detection_method' => 'Ultrasonic + IR sensors',
                        'pedestrian_status' => 'Crossing occupied',
                        'vehicle_status' => 'Present in marked crossing',
                        'device_status' => 'Warning light inspection needed',
                        'alert_transmission_status' => 'Successfully dispatched',
                        'evidence_source' => 'Camera module',
                        'event_description' => 'A vehicle entered the marked pedestrian crossing while a pedestrian was detected in the crossing zone.',
                    ],
                );

                if ($incident['code'] === 'CG-2026-001') {
                    foreach ([
                        ['code' => 'detected', 'title' => 'Potential incident created', 'description' => 'Crossing sensors recorded a potential vehicle and pedestrian conflict.', 'when' => $incident['when']],
                        ['code' => 'alert_dispatched', 'title' => 'Alert dispatched to enforcers', 'description' => 'An alert was sent to the authorized monitoring team.', 'when' => $incident['when']->copy()->addMinute()],
                    ] as $event) {
                        CrossingIncidentEvent::updateOrCreate(
                            ['crossing_incident_id' => $record->id, 'event_code' => $event['code']],
                            [
                                'user_id' => null,
                                'title' => $event['title'],
                                'description' => $event['description'],
                                'occurred_at' => $event['when'],
                            ],
                        );
                    }
                }
            }

            CrossingIssue::updateOrCreate(
                ['team_id' => $team->id, 'reference_code' => 'ISS-2026-001'],
                [
                    'reported_by' => $users['officer@crossguard.test']->id,
                    'title' => 'LED warning light needs inspection',
                    'description' => 'One warning light is intermittent during the crossing cycle.',
                    'status' => 'open',
                    'reported_at' => $now->copy()->subMinutes(18),
                ],
            );

            foreach ([
                ['code' => 'NTF-2026-001', 'title' => 'Potential violation detected', 'message' => 'A vehicle was detected inside the marked crossing.', 'level' => 'alert', 'read_at' => null, 'when' => $now->copy()->subMinutes(26)],
                ['code' => 'NTF-2026-002', 'title' => 'Device check needed', 'message' => 'LED warning lights reported an intermittent connection.', 'level' => 'warning', 'read_at' => null, 'when' => $now->copy()->subMinutes(18)],
                ['code' => 'NTF-2026-003', 'title' => 'Evidence snapshot saved', 'message' => 'The incident record is ready for authorized review.', 'level' => 'info', 'read_at' => $now->copy()->subMinutes(10), 'when' => $now->copy()->subMinutes(10)],
            ] as $notification) {
                CrossingNotification::updateOrCreate(
                    ['team_id' => $team->id, 'notification_code' => $notification['code']],
                    [
                        'title' => $notification['title'],
                        'message' => $notification['message'],
                        'level' => $notification['level'],
                        'read_at' => $notification['read_at'],
                        'occurred_at' => $notification['when'],
                    ],
                );
            }
        });
    }
}
