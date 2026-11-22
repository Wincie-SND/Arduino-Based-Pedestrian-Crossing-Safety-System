<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $crossing_incident_id
 * @property int|null $user_id
 * @property string $event_code
 * @property string $title
 * @property string|null $description
 * @property Carbon $occurred_at
 * @property-read CrossingIncident $incident
 * @property-read User|null $user
 */
#[Fillable(['crossing_incident_id', 'user_id', 'event_code', 'title', 'description', 'occurred_at'])]
class CrossingIncidentEvent extends Model
{
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /** @return BelongsTo<CrossingIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CrossingIncident::class, 'crossing_incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
