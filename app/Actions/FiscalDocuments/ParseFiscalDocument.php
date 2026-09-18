<?php

namespace App\Actions\FiscalDocuments;

use App\Enums\FiscalDocumentStatus;
use App\Events\FiscalDocuments\DocumentFailed;
use App\Events\FiscalDocuments\DocumentProcessed;
use App\Exceptions\FiscalExtractionException;
use App\Models\FiscalDocument;
use Illuminate\Support\Facades\Storage;
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

            $rawContent = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

            $xml = new SimpleXMLElement($rawContent);
            $info = $xml->infoTributaria ?? null;
            $totals = $xml->infoFactura ?? $xml->infoNotaCredito ?? $xml->infoNotaDebito ?? null;

            $receiverRuc = (string) ($totals->identificacionComprador ?? '');

            // Conversión genérica y completa del XML: cubre infoTributaria, infoFactura/
            // infoNotaCredito/infoNotaDebito con TODOS sus campos, detalles (con impuestos y
            // detallesAdicionales por atributo), infoAdicional (campoAdicional por atributo),
            // y totalConImpuestos/pagos como arrays — sin hardcodear campo por campo, para
            // que funcione igual con cualquier tipo de comprobante (factura, N/C, N/D,
            // retención) sin perder datos nuevos que el SRI agregue.
            $documento = $this->xmlElementToArray($xml);
            unset($documento['Signature']); // firma XAdES: certificado + digest, ya está íntegro en el XML crudo guardado; no es "dato del documento".

            $normalized = [
                // Campos planos de conveniencia (se mantienen por compatibilidad con lo ya
                // consumido en otras partes del sistema).
                'clave_acceso' => (string) ($info->claveAcceso ?? $document->access_key),
                'razon_social_emisor' => (string) ($info->razonSocial ?? ''),
                'razon_social_receptor' => (string) ($totals->razonSocialComprador ?? $receiverRuc),
                'forma_pago' => isset($totals->pagos) ? (string) ($totals->pagos->pago->formaPago ?? '') : null,
                // Documento completo — TODOS los datos del XML (infoTributaria, infoFactura/
                // infoNotaCredito/infoNotaDebito, detalles, infoAdicional), sin recortes.
                'documento' => $documento,
            ];

            $document->update([
                'status' => FiscalDocumentStatus::Processed->value,
                'issuer_ruc' => (string) ($info->ruc ?? $document->issuer_ruc ?? ''),
                'issuer_name' => $normalized['razon_social_emisor'] ?: null,
                'receiver_ruc' => $receiverRuc ?: $document->receiver_ruc,
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

    /**
     * Convierte un SimpleXMLElement en un array asociativo completo, preservando:
     * - Atributos del elemento, prefijados con '@' (ej. detAdicional nombre="X" valor="Y"
     *   se convierte en ['@nombre' => 'X', '@valor' => 'Y']).
     * - Elementos hijos repetidos (detalle, totalImpuesto, campoAdicional) como arrays
     *   indexados numéricamente en vez de sobrescribirse entre sí.
     * - Texto del elemento cuando no tiene hijos (o junto a atributos, bajo '@value').
     *
     * Genérico a propósito: funciona igual para factura, nota de crédito, nota de débito
     * o retención sin necesidad de mapear cada estructura a mano.
     */
    private function xmlElementToArray(SimpleXMLElement $element): array|string
    {
        $result = [];

        foreach ($element->attributes() as $key => $value) {
            $result['@'.$key] = (string) $value;
        }

        $children = $element->children();

        if ($children->count() === 0) {
            $text = trim((string) $element);

            if ($result === []) {
                return $text;
            }

            if ($text !== '') {
                $result['@value'] = $text;
            }

            return $result;
        }

        foreach ($children as $child) {
            $name = $child->getName();
            $value = $this->xmlElementToArray($child);

            if (array_key_exists($name, $result)) {
                if (! is_array($result[$name]) || ! array_key_exists(0, $result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
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