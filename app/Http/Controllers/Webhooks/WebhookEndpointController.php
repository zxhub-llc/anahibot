<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Webhooks\RegisterWebhookEndpoint;
use App\Http\Requests\Webhooks\StoreWebhookEndpointRequest;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Controllers\Controller;

class WebhookEndpointController extends Controller
{
    public function index(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        return Inertia::render('webhooks/index', [
            'webhooks' => $currentTeam->webhookEndpoints()
                ->latest()
                ->get()
                ->map(fn (WebhookEndpoint $endpoint) => (new WebhookEndpointResource($endpoint))->resolve()),
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request, Team $currentTeam, RegisterWebhookEndpoint $registerWebhookEndpoint): RedirectResponse
    {
        Gate::authorize('create', WebhookEndpoint::class);

        $registerWebhookEndpoint->handle($currentTeam, $request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint created.'),
        ]);
    }

    public function update(StoreWebhookEndpointRequest $request, Team $currentTeam, WebhookEndpoint $webhook): RedirectResponse
    {
        Gate::authorize('update', $webhook);

        $webhook->update($request->validated());

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint updated.'),
        ]);
    }

    public function destroy(Request $request, Team $currentTeam, WebhookEndpoint $webhook): RedirectResponse
    {
        Gate::authorize('delete', $webhook);

        $webhook->delete();

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint removed.'),
        ]);
    }
}
