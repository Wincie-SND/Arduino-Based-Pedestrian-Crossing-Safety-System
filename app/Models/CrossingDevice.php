<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $category
 * @property string $status
 * @property Carbon|null $last_seen_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'category', 'status', 'last_seen_at'])]
#[Hidden(['api_token_hash'])]
class CrossingDevice extends Model
{
    public const HEARTBEAT_TIMEOUT_SECONDS = 10;

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function getConnectionStatusAttribute(): string
    {
        if (
            in_array($this->status, ['online', 'warning'], true)
            && ($this->last_seen_at === null || $this->last_seen_at->lt(now()->subSeconds(self::HEARTBEAT_TIMEOUT_SECONDS)))
        ) {
            return 'offline';
        }

        return $this->status;
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
