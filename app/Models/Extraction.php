<?php

namespace App\Models;

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionTrigger;
use App\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extraction extends Model
{
    use HasFactory;
    use BelongsToCurrentTeam;

    protected $fillable = [
        'team_id',
        'requested_by_id',
        'company_ruc',
        'date_from',
        'date_to',
        'trigger',
        'status',
        'attempts',
        'completed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => ExtractionTrigger::class,
            'status' => ExtractionStatus::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
    
    public function runs(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function latestRun(): HasMany
    {
        return $this->runs()->latest('attempt_number');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }
}
