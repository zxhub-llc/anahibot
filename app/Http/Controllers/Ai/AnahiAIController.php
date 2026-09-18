<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Ai\AnahiAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnahiAIController extends Controller
{
    
    public function index(Request $request, Team $currentTeam): Response
    {
        return Inertia::render('ai/index', [
            'currentTeam' => [
                'id' => $currentTeam->id,
                'slug' => $currentTeam->slug,
                'name' => $currentTeam->name,
            ],
        ]);
    }
    
    public function chat(Request $request, Team $currentTeam, AnahiAiService $aiService): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'model' => ['nullable', 'string'],
        ]);

        $result = $aiService->processMessage(
            team: $currentTeam,
            user: $request->user(),
            userMessage: $request->string('message')->toString(),
            model: $request->input('model', 'gemini-2.0-flash')
        );

        return response()->json($result);
    }
}