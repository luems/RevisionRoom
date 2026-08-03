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

        session(["client_authenticated_{$share_token}" => true]);

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
            }, 'drafts.items', 'drafts.comments.user', 'drafts.comments.draftItem'])
            ->firstOrFail();

        session(["client_authenticated_{$share_token}" => true]);

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
        $project->changes_submitted_at = now();
        $project->touch(); // Touch updated_at so editor views catch the update

        return redirect()->back()->with('success', 'Revision batch submitted to editor.');
    }

    /**
     * Show the version comparison view for client
     */
    public function compare(Request $request, $share_token)
    {
        $project = Project::where('share_token', $share_token)
            ->with(['drafts' => function ($q) {
                $q->orderBy('version_number', 'desc');
            }, 'drafts.items'])
            ->firstOrFail();

        session(["client_authenticated_{$share_token}" => true]);

        // Auto-login the associated client if not logged in
        if ($project->client_id && (!Auth::check() || (Auth::user()->role === 'client' && Auth::id() !== $project->client_id))) {
            Auth::login($project->client);
        }

        $draft1Id = $request->query('draft1');
        $draft2Id = $request->query('draft2');

        // Fallback to latest two drafts if not provided
        if ((!$draft1Id || !$draft2Id) && $project->drafts->count() >= 2) {
            $draft1Id = $draft1Id ?: $project->drafts->last()->id; // Older version
            $draft2Id = $draft2Id ?: $project->drafts->first()->id; // Latest version
        }

        $draft1 = $project->drafts->firstWhere('id', $draft1Id);
        $draft2 = $project->drafts->firstWhere('id', $draft2Id);

        if ($draft1) {
            $draft1->load(['items', 'comments' => function($q) {
                $q->orderBy('timestamp_seconds', 'asc')->orderBy('created_at', 'asc');
            }, 'comments.user', 'comments.draftItem']);
        }
        if ($draft2) {
            $draft2->load(['items', 'comments' => function($q) {
                $q->orderBy('timestamp_seconds', 'asc')->orderBy('created_at', 'asc');
            }, 'comments.user', 'comments.draftItem']);
        }

        return Inertia::render('Project/Compare', [
            'project' => $project,
            'draft1' => $draft1,
            'draft2' => $draft2,
            'is_client' => true,
        ]);
    }
}
