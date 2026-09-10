<?php

namespace App\Http\Controllers\Sri;

use App\Actions\Sri\SaveSriCredentials;
use App\Actions\Sri\TestSriConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\SriCredentials\StoreSriCredentialRequest;
use App\Http\Resources\SriCredentialResource;
use App\Models\SriCredential;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SriCredentialController extends Controller
{
    public function edit(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('view', [SriCredential::class, $currentTeam]);

        $credential = SriCredential::query()->where('team_id', $currentTeam->id)->first();

        return Inertia::render('sri/edit', [
            'credential' => $credential ? (new SriCredentialResource($credential))->resolve() : null,
        ]);
    }

    public function update(StoreSriCredentialRequest $request, Team $currentTeam, SaveSriCredentials $saveSriCredentials): RedirectResponse
    {
        Gate::authorize('manage', [SriCredential::class, $currentTeam]);

        $saveSriCredentials->handle($currentTeam, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('SRI credentials saved.'),
        ]);
    }

    public function testConnection(Request $request, Team $currentTeam, TestSriConnection $testSriConnection): RedirectResponse
    {
        Gate::authorize('manage', [SriCredential::class, $currentTeam]);

        $credential = SriCredential::query()->where('team_id', $currentTeam->id)->firstOrFail();

        $success = $testSriConnection->handle($credential);

        return back()->with('toast', [
            'type' => $success ? 'success' : 'error',
            'message' => $success ? __('Connection successful.') : __('Connection failed: :error', ['error' => $credential->fresh()->last_authentication_error]),
        ]);
    }
}