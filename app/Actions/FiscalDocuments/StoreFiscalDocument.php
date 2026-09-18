<?php

namespace App\Actions\FiscalDocuments;

use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Enums\FiscalDocumentStatus;
use App\Events\FiscalDocuments\DocumentReceived;
use App\Models\ExtractionRun;
use App\Models\FiscalDocument;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;

class StoreFiscalDocument
{
    public function handle(
        Team $team,
        ExtractionRun $run,
        string $companyRuc,
        FiscalDocumentReference $reference,
        string $xmlContent,
    ): FiscalDocument {
        $existing = FiscalDocument::query()
            ->forTeam($team->id)
            ->where('access_key', $reference->accessKey)
            ->first();

        if ($existing !== null) {
            
            if ($this->isEnvelopedXml($existing)) {
                $existing
                    ->addMediaFromString($xmlContent)
                    ->usingFileName("{$reference->accessKey}.xml")
                    ->withCustomProperties(['mime' => 'application/xml'])
                    ->toMediaCollection(FiscalDocument::MEDIA_XML);
            }

            return $existing;
        }

        $document = FiscalDocument::query()->create([
            'team_id' => $team->id,
            'extraction_id' => $run->extraction_id,
            'extraction_run_id' => $run->id,
            'company_ruc' => $companyRuc,
            'access_key' => $reference->accessKey,
            'type' => $reference->type->value,
            'direction' => $reference->direction->value,
            'status' => FiscalDocumentStatus::Downloaded->value,
            'is_voided' => $reference->isVoided,
            'issuer_ruc' => $reference->issuerRuc,
            'receiver_ruc' => $reference->receiverRuc,
        ]);

        $document
            ->addMediaFromString($xmlContent)
            ->usingFileName("{$reference->accessKey}.xml")
            ->withCustomProperties(['mime' => 'application/xml'])
            ->toMediaCollection(FiscalDocument::MEDIA_XML);

        event(new DocumentReceived($document));

        return $document;
    }

    private function isEnvelopedXml(FiscalDocument $document): bool
    {
        $media = $document->xmlMedia();
        if ($media === null) {
            return false;
        }

        try {
            $content = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        } catch (\Throwable) {
            return false;
        }

        return str_contains($content, '<autorizacion>') && str_contains($content, '<comprobante>');
    }

    public function attachRide(FiscalDocument $document, string $rideBinary): FiscalDocument
    {
        $document
            ->addMediaFromString($rideBinary)
            ->usingFileName("{$document->access_key}.pdf")
            ->withCustomProperties(['mime' => 'application/pdf'])
            ->toMediaCollection(FiscalDocument::MEDIA_PDF);

        return $document->refresh();
    }

    public function handleWithoutXml(
        Team $team,
        ExtractionRun $run,
        string $companyRuc,
        FiscalDocumentReference $reference,
    ): FiscalDocument {
        $existing = FiscalDocument::query()
            ->forTeam($team->id)
            ->where('access_key', $reference->accessKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return FiscalDocument::query()->create([
            'team_id' => $team->id,
            'extraction_id' => $run->extraction_id,
            'extraction_run_id' => $run->id,
            'company_ruc' => $companyRuc,
            'access_key' => $reference->accessKey,
            'type' => $reference->type->value,
            'direction' => $reference->direction->value,
            'status' => FiscalDocumentStatus::Downloaded->value,
            'is_voided' => $reference->isVoided,
            'issuer_ruc' => $reference->issuerRuc,
            'receiver_ruc' => $reference->receiverRuc,
        ]);
    }

    public function attachNormalizedJson(FiscalDocument $document, array $payload): FiscalDocument
    {
        $document
            ->addMediaFromString(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
            ->usingFileName("{$document->access_key}.json")
            ->withCustomProperties(['mime' => 'application/json'])
            ->toMediaCollection(FiscalDocument::MEDIA_JSON);

        return $document->refresh();
    }
}