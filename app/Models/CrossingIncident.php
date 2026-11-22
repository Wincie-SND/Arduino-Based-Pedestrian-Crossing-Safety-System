<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $incident_code
 * @property string $incident_type
 * @property string $status
 * @property string|null $evidence_path
 * @property Carbon $occurred_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'incident_code', 'incident_type', 'status', 'evidence_path', 'occurred_at'])]
class CrossingIncident extends Model
{
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
