<?php

namespace App\Http\Controllers\Extractions;

use App\Actions\Extractions\CancelExtraction;
use App\Actions\Extractions\CreateExtraction;
use App\Actions\Extractions\RetryExtraction;
use App\Http\Requests\Extractions\StoreExtractionRequest;
use App\Http\Resources\ExtractionResource;
use App\Models\Extraction;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;

class ExtractionController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', Extraction::class);

        $extractions = $currentTeam->extractions()
            ->with('runs')
            ->latest()
            ->paginate(20)
            ->through(fn (Extraction $extraction) => (new ExtractionResource($extraction))->resolve());
        
            return Inertia::render('extractions/index', [
            'extractions' => $extractions,
        ]);
    }

    public function show(Request $request, Team $currentTeam, Extraction $extraction): Response
    {
        Gate::authorize('view', $extraction);

        return Inertia::render('extractions/show', [
            'extraction' => (new ExtractionResource($extraction->load('runs', 'documents')))->resolve(),
        ]);
    }

    public function store(StoreExtractionRequest $request, Team $currentTeam, CreateExtraction $createExtraction): RedirectResponse
{
    Gate::authorize('create', Extraction::class);

    $createExtraction->handle(
        user: $request->user(),
        team: $currentTeam,
        data: $request->validated(),
    );

    return back()->with('toast', [
        'type' => 'success',
        'message' => __('Extraction request created. Processing in the background.'),
    ]);
}

    public function retry(Request $request, Team $currentTeam, Extraction $extraction, RetryExtraction $retryExtraction): RedirectResponse
    {
        Gate::authorize('retry', $extraction);

        $retryExtraction->handle($extraction);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Extraction retry queued.'),
        ]);
    }

    public function cancel(Request $request, Team $currentTeam, Extraction $extraction, CancelExtraction $cancelExtraction): RedirectResponse
    {
        Gate::authorize('cancel', $extraction);

        $cancelExtraction->handle($extraction);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Extraction cancelled.'),
        ]);
    }
}
