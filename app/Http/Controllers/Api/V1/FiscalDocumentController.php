<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\FiscalDocumentResource;
use App\Models\FiscalDocument;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FiscalDocumentController
{
    public function index(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('viewAny', [FiscalDocument::class, $team]);

        $documents = $team->fiscalDocuments()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->string('direction')))
            ->when($request->boolean('voided'), fn ($query) => $query->where('is_voided', true))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return FiscalDocumentResource::collection($documents)->response();
    }

    public function show(Request $request, Team $team, FiscalDocument $document): JsonResponse
    {
        Gate::authorize('view', $document);

        return (new FiscalDocumentResource($document))->response();
    }
}
