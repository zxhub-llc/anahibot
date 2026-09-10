<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companies\SaveCompanyRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /**
     * Show the company edit page for the given team.
     */
    public function edit(Request $request, Team $team): Response
    {
        Gate::authorize('update', $team);

        return Inertia::render('companies/edit', [
            'team' => [
                'id' => $team->id,
                'slug' => $team->slug,
                'name' => $team->name,
            ],
            'company' => $team->company,
        ]);
    }

    /**
     * Update (or create) the company data for the given team.
     */
    public function update(SaveCompanyRequest $request, Team $team): RedirectResponse
    {
        Gate::authorize('update', $team);

        $team->company()->updateOrCreate(
            ['team_id' => $team->id],
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Updated company information.')]);

        return back();
    }
}