<?php

namespace App\Models;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FiscalDocument extends Model implements HasMedia
{
    use HasFactory;
    use BelongsToCurrentTeam;
    use InteractsWithMedia;

    public const MEDIA_XML = 'xml';
    public const MEDIA_PDF = 'pdf';
    public const MEDIA_JSON = 'json';

    protected $fillable = [
        'team_id',
        'extraction_id',
        'extraction_run_id',
        'company_ruc',
        'access_key',
        'type',
        'direction',
        'status',
        'is_voided',
        'issuer_ruc',
        'issuer_name',
        'receiver_ruc',
        'receiver_name',
        'issued_at',
        'subtotal',
        'tax_amount',
        'total',
        'normalized_metadata',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => FiscalDocumentType::class,
            'direction' => FiscalDocumentDirection::class,
            'status' => FiscalDocumentStatus::class,
            'is_voided' => 'boolean',
            'issued_at' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'normalized_metadata' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(Extraction::class);
    }

    public function extractionRun(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_XML)->useDisk('s3')->singleFile();
        $this->addMediaCollection(self::MEDIA_PDF)->useDisk('s3')->singleFile();
        $this->addMediaCollection(self::MEDIA_JSON)->useDisk('s3')->singleFile();
    }

    public function xmlMedia(): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_XML);
    }

    public function pdfMedia(): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_PDF);
    }

    public function jsonMedia(): ?Media
    {
        return $this->getFirstMedia(self::MEDIA_JSON);
    }

    public function hasXml(): bool
    {
        return $this->xmlMedia() !== null;
    }

    public function hasPdf(): bool
    {
        return $this->pdfMedia() !== null;
    }

    public function hasJson(): bool
    {
        return $this->jsonMedia() !== null;
    }
}
