<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $notification_code
 * @property string $title
 * @property string|null $message
 * @property string $level
 * @property Carbon $occurred_at
 * @property Carbon|null $read_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'notification_code', 'title', 'message', 'level', 'occurred_at', 'read_at'])]
class CrossingNotification extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
