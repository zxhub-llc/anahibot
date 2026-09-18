<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\Team;
use Carbon\Carbon;

class SriTaxCalculatorService
{
    
    public function calculateForm104(Team $team, ?string $month = null, ?string $year = null): array
    {
        $year = $year ?: date('Y');
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = $team->fiscalDocuments()
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->where('is_voided', false);

        $documents = $query->get();
        
        $salesDocs = $documents->filter(fn (FiscalDocument $doc) => 
            $doc->direction === FiscalDocumentDirection::Issued &&
            in_array($doc->type, [FiscalDocumentType::Invoice, FiscalDocumentType::DebitNote])
        );
        
        $salesCreditNotes = $documents->filter(fn (FiscalDocument $doc) => 
            $doc->direction === FiscalDocumentDirection::Issued &&
            $doc->type === FiscalDocumentType::CreditNote
        );
        
        $purchasesDocs = $documents->filter(fn (FiscalDocument $doc) => 
            $doc->direction === FiscalDocumentDirection::Received &&
            in_array($doc->type, [FiscalDocumentType::Invoice, FiscalDocumentType::PurchaseSettlement])
        );
        
        $purchasesCreditNotes = $documents->filter(fn (FiscalDocument $doc) => 
            $doc->direction === FiscalDocumentDirection::Received &&
            $doc->type === FiscalDocumentType::CreditNote
        );
        
        $retentionsReceived = $documents->filter(fn (FiscalDocument $doc) => 
            $doc->direction === FiscalDocumentDirection::Received &&
            $doc->type === FiscalDocumentType::Retention
        );

        $ventasSubtotal = $salesDocs->sum('subtotal') - $salesCreditNotes->sum('subtotal');
        $ventasIva = $salesDocs->sum('tax_amount') - $salesCreditNotes->sum('tax_amount');
        $ventasTotal = $salesDocs->sum('total') - $salesCreditNotes->sum('total');

        $comprasSubtotal = $purchasesDocs->sum('subtotal') - $purchasesCreditNotes->sum('subtotal');
        $comprasIva = $purchasesDocs->sum('tax_amount') - $purchasesCreditNotes->sum('tax_amount');
        $comprasTotal = $purchasesDocs->sum('total') - $purchasesCreditNotes->sum('total');

        $retencionesIvaSoportadas = $retentionsReceived->sum(function (FiscalDocument $doc) {
            $meta = $doc->normalized_metadata ?? [];
            return (float) ($meta['tax_retention_iva'] ?? 0);
        });
        
        $impuestoCausado = $ventasIva - $comprasIva - $retencionesIvaSoportadas;

        return [
            'period' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'period_label' => $startDate->locale('es')->translatedFormat('F Y'),
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'sales' => [
                'count' => $salesDocs->count(),
                'subtotal' => round(max(0, $ventasSubtotal), 2),
                'iva' => round(max(0, $ventasIva), 2),
                'total' => round(max(0, $ventasTotal), 2),
            ],
            'purchases' => [
                'count' => $purchasesDocs->count(),
                'subtotal' => round(max(0, $comprasSubtotal), 2),
                'iva' => round(max(0, $comprasIva), 2),
                'total' => round(max(0, $comprasTotal), 2),
            ],
            'retentions_supported' => round($retencionesIvaSoportadas, 2),
            'summary' => [
                'debito_fiscal' => round(max(0, $ventasIva), 2),
                'credito_fiscal' => round(max(0, $comprasIva), 2),
                'impuesto_causado' => round($impuestoCausado > 0 ? $impuestoCausado : 0, 2),
                'credito_tributario_resultante' => round($impuestoCausado < 0 ? abs($impuestoCausado) : 0, 2),
            ],
        ];
    }
    
    public function calculateForm103(Team $team, ?string $month = null, ?string $year = null): array
    {
        $year = $year ?: date('Y');
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $issuedRetentions = $team->fiscalDocuments()
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->where('is_voided', false)
            ->where('direction', FiscalDocumentDirection::Issued)
            ->where('type', FiscalDocumentType::Retention)
            ->get();

        $totalRetenidoRenta = 0;
        $details = [];

        foreach ($issuedRetentions as $doc) {
            $meta = $doc->normalized_metadata ?? [];
            $items = $meta['retention_details'] ?? [];
            
            $docRentaTotal = 0;
            if (is_array($items) && count($items) > 0) {
                foreach ($items as $item) {
                    if (($item['tax_code'] ?? '') === 'RENTA' || ($item['tax_type'] ?? '') === 'RENTA') {
                        $docRentaTotal += (float) ($item['retained_amount'] ?? 0);
                    }
                }
            } else {
                $docRentaTotal = (float) ($doc->tax_amount ?? 0);
            }

            $totalRetenidoRenta += $docRentaTotal;
            $details[] = [
                'access_key' => $doc->access_key,
                'receiver_name' => $doc->receiver_name,
                'receiver_ruc' => $doc->receiver_ruc,
                'issued_at' => $doc->issued_at?->toDateString(),
                'retained_amount' => round($docRentaTotal, 2),
            ];
        }

        return [
            'period' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'period_label' => $startDate->locale('es')->translatedFormat('F Y'),
            ],
            'count' => $issuedRetentions->count(),
            'total_retained_renta' => round($totalRetenidoRenta, 2),
            'retentions' => array_slice($details, 0, 10),
        ];
    }
    
    public function calculateAtsSummary(Team $team, ?string $month = null, ?string $year = null): array
    {
        $year = $year ?: date('Y');
        $month = $month ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m');

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $allDocs = $team->fiscalDocuments()
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->get();

        $issuedValid = $allDocs->where('direction', FiscalDocumentDirection::Issued)->where('is_voided', false);
        $issuedVoided = $allDocs->where('direction', FiscalDocumentDirection::Issued)->where('is_voided', true);
        $receivedValid = $allDocs->where('direction', FiscalDocumentDirection::Received)->where('is_voided', false);

        return [
            'period' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'period_label' => $startDate->locale('es')->translatedFormat('F Y'),
            ],
            'issued_documents' => [
                'valid_count' => $issuedValid->count(),
                'voided_count' => $issuedVoided->count(),
                'subtotal' => round($issuedValid->sum('subtotal'), 2),
                'iva' => round($issuedValid->sum('tax_amount'), 2),
                'total' => round($issuedValid->sum('total'), 2),
            ],
            'received_documents' => [
                'valid_count' => $receivedValid->count(),
                'subtotal' => round($receivedValid->sum('subtotal'), 2),
                'iva' => round($receivedValid->sum('tax_amount'), 2),
                'total' => round($receivedValid->sum('total'), 2),
            ],
            'total_transactions' => $allDocs->count(),
        ];
    }
    
    public function generateFinancialReport(Team $team, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $startDate = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $dateTo ? Carbon::parse($dateTo)->endOfDay() : now()->endOfDay();

        $docs = $team->fiscalDocuments()
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->where('is_voided', false)
            ->get();

        $byType = [];
        foreach (FiscalDocumentType::cases() as $typeCase) {
            $typeDocs = $docs->where('type', $typeCase);
            if ($typeDocs->count() > 0) {
                $byType[$typeCase->value] = [
                    'label' => match($typeCase) {
                        FiscalDocumentType::Invoice => 'Facturas',
                        FiscalDocumentType::CreditNote => 'Notas de Crédito',
                        FiscalDocumentType::DebitNote => 'Notas de Débito',
                        FiscalDocumentType::Retention => 'Retenciones',
                        FiscalDocumentType::PurchaseSettlement => 'Liquidaciones de Compra',
                        FiscalDocumentType::RemissionGuide => 'Guías de Remisión',
                    },
                    'count' => $typeDocs->count(),
                    'subtotal' => round($typeDocs->sum('subtotal'), 2),
                    'tax_amount' => round($typeDocs->sum('tax_amount'), 2),
                    'total' => round($typeDocs->sum('total'), 2),
                ];
            }
        }

        $issued = $docs->where('direction', FiscalDocumentDirection::Issued);
        $received = $docs->where('direction', FiscalDocumentDirection::Received);

        return [
            'range' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'totals' => [
                'total_documents' => $docs->count(),
                'issued_total' => round($issued->sum('total'), 2),
                'received_total' => round($received->sum('total'), 2),
                'net_balance' => round($issued->sum('total') - $received->sum('total'), 2),
            ],
            'by_type' => $byType,
        ];
    }
}
