<?php

namespace App\Models;

use App\Enums\ExtractionRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'extraction_id',
        'attempt_number',
        'status',
        'started_at',
        'finished_at',
        'documents_found',
        'documents_processed',
        'documents_failed',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExtractionRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(Extraction::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }
}
