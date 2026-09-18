<?php

namespace App\Http\Controllers;

use App\Enums\FiscalDocumentDirection;
use App\Models\SriCredential;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $email = strtolower($user->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        $data = [
            'pendingInvitations' => $pendingInvitations,

            'accountType' => $user->account_type->value,

            'isAccountant' => $user->isAccountant(),

            'currentTeam' => null,

            'company' => null,

            'sriStatus' => null,

            'sri' => null,

            'summary' => null,

            'recentExtractions' => [],
        ];

        $currentTeam = Team::find($user->current_team_id);

        if ($currentTeam) {
            $company = $currentTeam->company;

            $data['currentTeam'] = [
                'id' => $currentTeam->id,
                'name' => $currentTeam->name,
                'slug' => $currentTeam->slug,
                'type' => $currentTeam->type->value,
            ];

            $profile = [
                'ruc' => $company?->ruc,
                'business_name' => $company?->business_name,
                'email' => data_get($company, 'email'),
                'phone' => data_get($company, 'phone'),
                'legal_representative' => data_get(
                    $company,
                    'legal_representative',
                ),
                'legal_representative_id' => data_get(
                    $company,
                    'legal_representative_id',
                ),
            ];

            $data['company'] = $profile;

            $data['sriStatus'] = $this->buildSriStatus(
                $currentTeam,
            );

            $data['sri'] = [
                'profile' => $profile,

                /*
                 * Estas dos colecciones quedan preparadas para recibir
                 * los datos reales obtenidos desde el SRI.
                 */
                'notifications' => [],

                'obligations' => [],
            ];

            $data['summary'] = $this->buildSummary(
                $currentTeam,
            );

            $data['recentExtractions'] = $currentTeam
                ->extractions()
                ->latest()
                ->limit(5)
                ->get([
                    'id',
                    'date_from',
                    'date_to',
                    'status',
                    'completed_at',
                ])
                ->map(fn ($extraction) => [
                    'id' => $extraction->id,
                    'date_from' => optional(
                        $extraction->date_from,
                    )->toDateString(),
                    'date_to' => optional(
                        $extraction->date_to,
                    )->toDateString(),
                    'status' => $extraction->status->value,
                    'completed_at' => optional(
                        $extraction->completed_at,
                    )->toDateTimeString(),
                ])
                ->values()
                ->all();
        }

        if ($user->isAccountant()) {
            $data['clientTeams'] = $user->teams()
                ->withCount('fiscalDocuments')
                ->with('company')
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'ruc' => $team->company?->ruc,
                    'documentsCount' => $team->fiscal_documents_count,
                ])
                ->values()
                ->all();
        } else {
            $data['clientTeams'] = [];
        }

        return Inertia::render('dashboard', $data);
    }

    private function buildSriStatus(Team $team): array
    {
        $credential = SriCredential::query()
            ->where('team_id', $team->id)
            ->first();

        return [
            'configured' => $credential !== null,

            'last_authenticated_at' => optional(
                $credential?->last_authenticated_at,
            )->toDateTimeString(),

            'has_error' => $credential?->last_authentication_error !== null,
        ];
    }

    /**
     * Resumen de ventas (emitidos) y compras (recibidos),
     * excluyendo documentos anulados.
     */
    private function buildSummary(Team $team): array
    {
        $byDirection = function (
            string $direction
        ) use ($team): array {
            $rows = $team->fiscalDocuments()
                ->where('direction', $direction)
                ->where('is_voided', false)
                ->selectRaw(
                    'type,
                    SUM(subtotal) as subtotal,
                    SUM(tax_amount) as tax_amount,
                    SUM(total) as total,
                    COUNT(*) as count'
                )
                ->groupBy('type')
                ->get();

            return [
                'total' => (float) $rows->sum('total'),

                'subtotal' => (float) $rows->sum('subtotal'),

                'tax_amount' => (float) $rows->sum(
                    'tax_amount'
                ),

                'by_type' => $rows
                    ->map(fn ($row) => [
                        'type' => $row->type,
                        'total' => (float) $row->total,
                        'count' => (int) $row->count,
                    ])
                    ->values()
                    ->all(),
            ];
        };

        return [
            'sales' => $byDirection(
                FiscalDocumentDirection::Issued->value
            ),

            'purchases' => $byDirection(
                FiscalDocumentDirection::Received->value
            ),

            'voided_count' => $team
                ->fiscalDocuments()
                ->where('is_voided', true)
                ->count(),
        ];
    }
}