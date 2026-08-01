<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::where('editor_id', Auth::id())
            ->with(['client', 'latestDraft'])
            ->withCount('drafts')
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Editor/Dashboard', [
            'projects' => $projects
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
        ]);

        // Find or create client user
        $client = User::where('email', $request->client_email)->first();
        if (!$client) {
            $client = User::create([
                'name' => $request->client_name,
                'email' => $request->client_email,
                'role' => 'client',
                'password' => null, // Passwordless magic link access
            ]);
        }

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'editor_id' => Auth::id(),
            'client_id' => $client->id,
            'share_token' => Str::random(32),
            'status' => 'active'
        ]);

        return redirect()->route('projects.show', $project->id)
            ->with('success', 'Project created successfully.');
    }

    public function show($id)
    {
        $project = Project::where('editor_id', Auth::id())
            ->with(['client', 'drafts' => function ($q) {
                $q->orderBy('version_number', 'desc');
            }, 'drafts.comments.user'])
            ->findOrFail($id);

        return Inertia::render('Project/Show', [
            'project' => $project
        ]);
    }

    public function compare($id, Request $request)
    {
        $project = Project::where('editor_id', Auth::id())
            ->with(['drafts' => function ($q) {
                $q->orderBy('version_number', 'desc');
            }])
            ->findOrFail($id);

        $draft1Id = $request->query('draft1');
        $draft2Id = $request->query('draft2');

        // Fallback to latest two drafts if missing
        if ((!$draft1Id || !$draft2Id) && $project->drafts->count() >= 2) {
            $draft1Id = $draft1Id ?: $project->drafts->last()->id;
            $draft2Id = $draft2Id ?: $project->drafts->first()->id;
        }

        $draft1 = $project->drafts->firstWhere('id', $draft1Id);
        $draft2 = $project->drafts->firstWhere('id', $draft2Id);

        if ($draft1) {
            $draft1->load(['comments' => function($q) {
                $q->orderBy('timestamp_seconds', 'asc');
            }, 'comments.user']);
        }
        if ($draft2) {
            $draft2->load(['comments' => function($q) {
                $q->orderBy('timestamp_seconds', 'asc');
            }, 'comments.user']);
        }

        return Inertia::render('Project/Compare', [
            'project' => $project,
            'draft1' => $draft1,
            'draft2' => $draft2,
            'is_client' => false,
        ]);
    }

    public function destroy($id)
    {
        $project = Project::where('editor_id', Auth::id())->findOrFail($id);
        $project->delete();

        return redirect()->route('dashboard')
            ->with('success', 'Project deleted successfully.');
    }

    public function archive($id)
    {
        $project = Project::where('editor_id', Auth::id())->findOrFail($id);
        $project->status = 'archived';
        $project->save();

        return redirect()->route('dashboard')
            ->with('success', 'Project archived and locked.');
    }
}
