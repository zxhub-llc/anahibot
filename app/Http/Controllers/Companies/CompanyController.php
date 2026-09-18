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
    public function edit(Request $request, Team $currentTeam): Response
    {
        Gate::authorize('update', $currentTeam);

        return Inertia::render('companies/edit', [
            'team' => [
                'id' => $currentTeam->id,
                'slug' => $currentTeam->slug,
                'name' => $currentTeam->name,
            ],
            'company' => $currentTeam->company,
        ]);
    }

    public function update(
        SaveCompanyRequest $request,
        Team $currentTeam
    ): RedirectResponse {
        Gate::authorize('update', $currentTeam);

        $currentTeam->company()->updateOrCreate(
            ['team_id' => $currentTeam->id],
            $request->validated(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Updated company information.'),
        ]);

        return back();
    }
}