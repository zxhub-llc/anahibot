<?php

namespace App\Http\Controllers\FiscalDocuments;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Http\Resources\FiscalDocumentResource;
use App\Models\FiscalDocument;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;

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
        ]);
    }

    public function show(Request $request, Team $currentTeam, FiscalDocument $document): Response
    {
        Gate::authorize('view', $document);

        return Inertia::render('fiscal-documents/show', [
            'document' => (new FiscalDocumentResource($document))->resolve(),
        ]);
    }

    public function downloadXml(Request $request, Team $currentTeam, FiscalDocument $document): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->temporaryRedirect($document, FiscalDocument::MEDIA_XML);
    }

    public function downloadPdf(Request $request, Team $currentTeam, FiscalDocument $document): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->temporaryRedirect($document, FiscalDocument::MEDIA_PDF);
    }

    public function downloadJson(Request $request, Team $currentTeam, FiscalDocument $document): RedirectResponse
    {
        Gate::authorize('view', $document);

        return $this->temporaryRedirect($document, FiscalDocument::MEDIA_JSON);
    }

    private function temporaryRedirect(FiscalDocument $document, string $collection): RedirectResponse
    {
        $media = $document->getFirstMedia($collection);

        abort_if($media === null, 404);

        return redirect()->away($media->getTemporaryUrl(now()->addMinutes(5)));
    }
}