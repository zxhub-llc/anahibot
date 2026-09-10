<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Extractions\CreateExtraction;
use App\Http\Requests\Extractions\StoreExtractionRequest;
use App\Http\Resources\ExtractionResource;
use App\Models\Extraction;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExtractionController
{
    public function index(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('viewAny', [Extraction::class, $team]);

        $extractions = $team->extractions()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ExtractionResource::collection($extractions)->response();
    }

    public function show(Request $request, Team $team, Extraction $extraction): JsonResponse
    {
        Gate::authorize('view', $extraction);

        return (new ExtractionResource($extraction))->response();
    }

    public function store(StoreExtractionRequest $request, Team $team, CreateExtraction $createExtraction): JsonResponse
    {
        Gate::authorize('create', [Extraction::class, $team]);

        $extraction = $createExtraction->handle(
            user: $request->user(),
            team: $team,
            data: $request->validated(),
        );

        return (new ExtractionResource($extraction))->response()->setStatusCode(201);
    }
}
