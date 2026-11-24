<?php

namespace App\Console\Commands;

use App\Models\CrossingDevice;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueCrossingDeviceToken extends Command
{
    protected $signature = 'crossguard:device-token {device : The device ID or exact device name}';

    protected $description = 'Issue or rotate the API token for an Arduino crossing device';

    public function handle(): int
    {
        $identifier = (string) $this->argument('device');
        $query = CrossingDevice::query();

        if (ctype_digit($identifier)) {
            $query->whereKey((int) $identifier);
        } else {
            $query->where('name', $identifier);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->error('No matching crossing device was found.');

            return self::FAILURE;
        }

        if ($devices->count() > 1) {
            $this->error('That device name is used by more than one crossing. Select a device by its ID instead.');
            $this->table(['ID', 'Name', 'Team ID'], $devices->map(fn (CrossingDevice $device): array => [
                $device->getKey(),
                $device->name,
                $device->team_id,
            ]));

            return self::FAILURE;
        }

        $device = $devices->first();
        $token = Str::random(64);
        $device->api_token_hash = hash('sha256', $token);
        $device->save();

        $this->info('Copy this token into arduino/CrossGuardController/secrets.h. It will not be shown again:');
        $this->line($token);

        return self::SUCCESS;
    }
}
