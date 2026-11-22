<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $incident_code
 * @property string $incident_type
 * @property string $status
 * @property string|null $evidence_path
 * @property string|null $detection_method
 * @property string|null $pedestrian_status
 * @property string|null $vehicle_status
 * @property string|null $device_status
 * @property string|null $alert_transmission_status
 * @property string|null $evidence_source
 * @property string|null $event_description
 * @property int|null $assigned_enforcer_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_remarks
 * @property Carbon $occurred_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'incident_code', 'incident_type', 'status', 'evidence_path', 'occurred_at', 'detection_method', 'pedestrian_status', 'vehicle_status', 'device_status', 'alert_transmission_status', 'evidence_source', 'event_description', 'assigned_enforcer_id', 'reviewed_by', 'reviewed_at', 'review_remarks'])]
class CrossingIncident extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedEnforcer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_enforcer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CrossingIncidentEvent::class)->orderBy('occurred_at');
    }
}
