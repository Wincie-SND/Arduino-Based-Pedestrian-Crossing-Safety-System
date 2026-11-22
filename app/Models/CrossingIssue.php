<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $reported_by
 * @property string $reference_code
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property Carbon $reported_at
 * @property-read Team $team
 * @property-read User|null $reporter
 */
#[Fillable(['team_id', 'reported_by', 'reference_code', 'title', 'description', 'status', 'reported_at'])]
class CrossingIssue extends Model
{
    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
