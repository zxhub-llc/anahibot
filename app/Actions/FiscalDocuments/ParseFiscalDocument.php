<?php

namespace App\Actions\FiscalDocuments;

use App\Enums\FiscalDocumentStatus;
use App\Events\FiscalDocuments\DocumentFailed;
use App\Events\FiscalDocuments\DocumentProcessed;
use App\Exceptions\FiscalExtractionException;
use App\Models\FiscalDocument;
use SimpleXMLElement;
use Throwable;

class ParseFiscalDocument
{
    public function __construct(private readonly StoreFiscalDocument $storeFiscalDocument)
    {
    }

    public function handle(FiscalDocument $document): FiscalDocument
    {
        try {
            $media = $document->xmlMedia();

            if ($media === null) {
                throw FiscalExtractionException::missingXml($document);
            }

            $xml = new SimpleXMLElement($media->getContent());
            $info = $xml->infoTributaria ?? null;
            $totals = $xml->infoFactura ?? $xml->infoNotaCredito ?? $xml->infoNotaDebito ?? null;

            $normalized = [
                'clave_acceso' => (string) ($info->claveAcceso ?? $document->access_key),
                'razon_social_emisor' => (string) ($info->razonSocial ?? ''),
                'razon_social_receptor' => (string) ($totals->razonSocialComprador ?? $totals->identificacionComprador ?? ''),
                'forma_pago' => isset($totals->pagos) ? (string) ($totals->pagos->pago->formaPago ?? '') : null,
            ];

            $document->update([
                'status' => FiscalDocumentStatus::Processed->value,
                'issuer_ruc' => (string) ($info->ruc ?? $document->issuer_ruc ?? ''),
                'issuer_name' => $normalized['razon_social_emisor'] ?: null,
                'receiver_name' => $normalized['razon_social_receptor'] ?: null,
                'issued_at' => $this->parseDate((string) ($totals->fechaEmision ?? '')),
                'subtotal' => (float) ($totals->totalSinImpuestos ?? 0),
                'tax_amount' => $this->sumTaxes($totals),
                'total' => (float) ($totals->importeTotal ?? 0),
                'normalized_metadata' => $normalized,
                'processing_error' => null,
            ]);

            $document = $document->refresh();

            $this->storeFiscalDocument->attachNormalizedJson($document, array_merge(
                $document->only([
                    'id', 'company_ruc', 'access_key', 'type', 'direction', 'is_voided',
                    'issuer_ruc', 'issuer_name', 'receiver_ruc', 'receiver_name',
                    'issued_at', 'subtotal', 'tax_amount', 'total',
                ]),
                ['normalized_metadata' => $normalized],
            ));

            $document = $document->refresh();

            event(new DocumentProcessed($document));

            return $document;
        } catch (Throwable $exception) {
            $document->update([
                'status' => FiscalDocumentStatus::Failed->value,
                'processing_error' => $exception->getMessage(),
            ]);

            $document = $document->refresh();

            event(new DocumentFailed($document));

            return $document;
        }
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $parts = explode('/', $value);

        if (count($parts) !== 3) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
    }

    private function sumTaxes(?SimpleXMLElement $totals): float
    {
        if ($totals === null || ! isset($totals->totalConImpuestos)) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($totals->totalConImpuestos->totalImpuesto as $impuesto) {
            $sum += (float) ($impuesto->valor ?? 0);
        }

        return $sum;
    }
}