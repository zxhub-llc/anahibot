<?php

namespace App\Http\Controllers\FiscalDocuments;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Http\Resources\FiscalDocumentResource;
use App\Models\FiscalDocument;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FiscalDocumentController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', [FiscalDocument::class, $currentTeam]);

        $documents = $currentTeam->fiscalDocuments()
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->string('direction')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->boolean('voided'), fn ($query) => $query->where('is_voided', true))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (FiscalDocument $document) => (new FiscalDocumentResource($document))->resolve());

        return Inertia::render('fiscal-documents/index', [
            'documents' => $documents,
            'filters' => $request->only(['direction', 'type', 'voided']),
            'documentTypes' => array_map(fn (FiscalDocumentType $type) => $type->value, FiscalDocumentType::cases()),
            'directions' => array_map(fn (FiscalDocumentDirection $direction) => $direction->value, FiscalDocumentDirection::cases()),
            'current_team' => ['slug' => $currentTeam->slug],
            'summary' => $this->buildSummary($currentTeam),
            'chartSeries' => $this->buildChartSeries($currentTeam),
        ]);
    }

    public function show(Request $request, Team $currentTeam, FiscalDocument $document): Response
    {
        Gate::authorize('view', $document);

        return Inertia::render('fiscal-documents/show', [
            'document' => (new FiscalDocumentResource($document))->resolve(),
            'current_team' => ['slug' => $currentTeam->slug],
        ]);
    }

    public function downloadXml(Request $request, Team $currentTeam, FiscalDocument $document): SymfonyResponse
    {
        Gate::authorize('view', $document);

        $media = $document->getFirstMedia(FiscalDocument::MEDIA_XML);

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), "{$document->access_key}.xml", [
                'Content-Type' => 'application/xml',
            ]);
        }

        if ($media) {
            try {
                return redirect()->away($media->getTemporaryUrl(now()->addMinutes(5)));
            } catch (\Throwable $e) {
                // fallback
            }
        }

        abort(404, __('Archivo XML no encontrado para este comprobante.'));
    }

    public function downloadPdf(Request $request, Team $currentTeam, FiscalDocument $document): SymfonyResponse
    {
        Gate::authorize('view', $document);

        $media = $document->getFirstMedia(FiscalDocument::MEDIA_PDF);

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), "{$document->access_key}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        }

        if ($media) {
            try {
                return redirect()->away($media->getTemporaryUrl(now()->addMinutes(5)));
            } catch (\Throwable $e) {
                // fallback
            }
        }

        abort(404, __('Archivo RIDE/PDF no encontrado para este comprobante.'));
    }

    public function downloadJson(Request $request, Team $currentTeam, FiscalDocument $document): SymfonyResponse
    {
        Gate::authorize('view', $document);

        $media = $document->getFirstMedia(FiscalDocument::MEDIA_JSON);

        if ($media && file_exists($media->getPath())) {
            return response()->download($media->getPath(), "{$document->access_key}.json", [
                'Content-Type' => 'application/json',
            ]);
        }

        if ($media) {
            try {
                return redirect()->away($media->getTemporaryUrl(now()->addMinutes(5)));
            } catch (\Throwable $e) {
                // fallback
            }
        }

        // Generar JSON al vuelo si existen metadatos normalizados
        if (!empty($document->normalized_metadata)) {
            return response()->streamDownload(
                fn () => print(json_encode($document->normalized_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
                "{$document->access_key}.json",
                ['Content-Type' => 'application/json']
            );
        }

        abort(404, __('Archivo JSON no encontrado para este comprobante.'));
    }

    public function downloadZip(Request $request, Team $currentTeam, FiscalDocument $document): SymfonyResponse
    {
        Gate::authorize('view', $document);

        $files = collect([
            'xml' => $document->getFirstMedia(FiscalDocument::MEDIA_XML),
            'pdf' => $document->getFirstMedia(FiscalDocument::MEDIA_PDF),
            'json' => $document->getFirstMedia(FiscalDocument::MEDIA_JSON),
        ])->filter(fn ($media) => $media !== null && file_exists($media->getPath()));

        if ($files->isEmpty()) {
            abort(404, __('No hay archivos disponibles para este comprobante.'));
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $zipPath = "{$tmpDir}/{$document->access_key}.zip";

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $extension => $media) {
            $zip->addFile($media->getPath(), "{$document->access_key}.{$extension}");
        }

        $zip->close();

        return response()->download($zipPath, "{$document->access_key}.zip")->deleteFileAfterSend(true);
    }

    /**
     * Construye el resumen de ventas (emitidos) y compras (recibidos), excluyendo
     * documentos anulados, con desglose por tipo de documento.
     */
    private function buildSummary(Team $team): array
    {
        $byDirection = function (string $direction) use ($team): array {
            $rows = $team->fiscalDocuments()
                ->where('direction', $direction)
                ->where('is_voided', false)
                ->selectRaw('type, SUM(subtotal) as subtotal, SUM(tax_amount) as tax_amount, SUM(total) as total, COUNT(*) as count')
                ->groupBy('type')
                ->get();

            return [
                'total' => (float) $rows->sum('total'),
                'subtotal' => (float) $rows->sum('subtotal'),
                'tax_amount' => (float) $rows->sum('tax_amount'),
                'by_type' => $rows->map(fn ($row) => [
                    'type' => $row->type,
                    'total' => (float) $row->total,
                    'count' => (int) $row->count,
                ])->values()->all(),
            ];
        };

        return [
            'sales' => $byDirection(FiscalDocumentDirection::Issued->value),
            'purchases' => $byDirection(FiscalDocumentDirection::Received->value),
            'voided_count' => $team->fiscalDocuments()->where('is_voided', true)->count(),
        ];
    }

    /**
     * Construye la serie diaria (últimos 90 días) de montos emitidos vs. recibidos
     * para el gráfico de área, excluyendo documentos anulados.
     */
    private function buildChartSeries(Team $team): array
    {
        $rows = $team->fiscalDocuments()
            ->where('is_voided', false)
            ->whereNotNull('issued_at')
            ->where('issued_at', '>=', now()->subDays(90)->startOfDay())
            ->selectRaw('issued_at as date, direction, SUM(total) as total')
            ->groupBy('issued_at', 'direction')
            ->orderBy('issued_at')
            ->get();

        $byDate = [];

        foreach ($rows as $row) {
            $date = (string) $row->date;

            if (! isset($byDate[$date])) {
                $byDate[$date] = ['date' => $date, 'issued' => 0.0, 'received' => 0.0];
            }

            $key = $row->direction === FiscalDocumentDirection::Issued->value ? 'issued' : 'received';
            $byDate[$date][$key] += (float) $row->total;
        }

        ksort($byDate);

        return array_values($byDate);
    }
}