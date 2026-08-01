<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request, $draftId)
    {
        $draft = Draft::findOrFail($draftId);

        $request->validate([
            'content' => 'required|string',
            'timestamp_seconds' => 'nullable|numeric',
            'image' => 'nullable|image|max:10240', // Max 10MB
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('comments', 'public');
        }

        $comment = Comment::create([
            'draft_id' => $draft->id,
            'user_id' => Auth::id(),
            'author_name' => Auth::user()?->name ?? $request->input('author_name', 'Client'),
            'content' => $request->content,
            'timestamp_seconds' => $request->timestamp_seconds,
            'is_resolved' => false,
            'image_path' => $imagePath,
        ]);

        return redirect()->back()->with('success', 'Comment added.');
    }

    public function resolve(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        $comment->is_resolved = $request->input('is_resolved', true);
        $comment->resolved_at = $comment->is_resolved ? now() : null;
        $comment->resolved_by = $comment->is_resolved ? Auth::id() : null;
        $comment->save();

        return redirect()->back()->with('success', $comment->is_resolved ? 'Comment resolved.' : 'Comment unresolved.');
    }

    public function reject(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        $comment->is_rejected = $request->input('is_rejected', true);
        $comment->rejection_reason = $request->input('rejection_reason');

        // Rejection clears resolution status
        if ($comment->is_rejected) {
            $comment->is_resolved = false;
            $comment->resolved_at = null;
            $comment->resolved_by = null;
        }

        $comment->save();

        return redirect()->back()->with('success', $comment->is_rejected ? 'Comment marked as not doable.' : 'Rejection cleared.');
    }

    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return redirect()->back()->with('success', 'Comment deleted successfully.');
    }
}
