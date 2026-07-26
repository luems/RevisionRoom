<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Draft;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class ApprovalController extends Controller
{
    public function store(Request $request, $draftId)
    {
        $draft = Draft::with('project')->findOrFail($draftId);
        $project = $draft->project;

        $request->validate([
            'approver_name' => 'required|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $approval = Approval::create([
            'project_id' => $project->id,
            'draft_id' => $draft->id,
            'user_id' => Auth::id(),
            'approver_name' => $request->approver_name,
            'remarks' => $request->remarks,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Update project status to approved
        $project->status = 'approved';
        $project->save();

        return redirect()->back()->with('success', 'Draft approved successfully!');
    }

    public function downloadRecord($projectId)
    {
        $project = Project::with(['client', 'editor'])->findOrFail($projectId);

        // Find latest approval
        $approval = Approval::where('project_id', $project->id)->latest()->first();

        // Fallback to latest draft if not approved
        $draft = $approval
            ? $approval->draft
            : $project->drafts()->first();

        if (!$draft) {
            return redirect()->back()->with('error', 'No drafts found for this project.');
        }

        $comments = $draft->comments()->with('user')->get();

        $pdf = Pdf::loadView('pdf.approval_record', [
            'project' => $project,
            'draft' => $draft,
            'approval' => $approval,
            'comments' => $comments,
        ]);

        $filename = 'approval_record_' . Str::slug($project->name) . '_v' . $draft->version_number . '.pdf';

        return $pdf->download($filename);
    }

    public function destroy($projectId)
    {
        $project = Project::findOrFail($projectId);

        if ($project->status !== 'approved') {
            return redirect()->back()->with('error', 'Cannot cancel approval for this project status.');
        }

        // Delete approval record
        Approval::where('project_id', $project->id)->delete();

        // Revert status back to active
        $project->status = 'active';
        $project->save();

        return redirect()->back()->with('success', 'Project approval cancelled.');
    }
}
