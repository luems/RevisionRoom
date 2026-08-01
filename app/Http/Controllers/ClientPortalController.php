<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientPortalController extends Controller
{
    /**
     * Authenticate client via magic link share_token
     */
    public function loginWithToken(Request $request, $share_token)
    {
        $project = Project::where('share_token', $share_token)->firstOrFail();

        if ($project->client) {
            Auth::login($project->client);
        }

        return redirect()->route('client.projects.show', $project->share_token);
    }

    /**
     * Show the client review page
     */
    public function showProject(Request $request, $share_token)
    {
        $project = Project::where('share_token', $share_token)
            ->with(['editor', 'drafts' => function ($q) {
                $q->orderBy('version_number', 'desc');
            }, 'drafts.comments.user'])
            ->firstOrFail();

        // Auto-login the associated client if not logged in
        if ($project->client_id && (!Auth::check() || (Auth::user()->role === 'client' && Auth::id() !== $project->client_id))) {
            Auth::login($project->client);
        }

        return Inertia::render('Client/Review', [
            'project' => $project,
            'auth_user' => Auth::user() ? [
                'id' => Auth::id(),
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'role' => Auth::user()->role,
            ] : null,
        ]);
    }

    /**
     * Mark current feedback changes as requested for the editor
     */
    public function markChangesRequested(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        if ($project->status !== 'approved' && $project->status !== 'archived') {
            $project->status = 'active';
        }
        $project->touch(); // Touch updated_at so editor views catch the update

        return redirect()->back()->with('success', 'Marked current changes as ready for editor.');
    }
}
