<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
class CrossingDevice extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
