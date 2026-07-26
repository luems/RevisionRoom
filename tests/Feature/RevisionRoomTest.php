<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Draft;
use App\Models\Comment;
use App\Models\Approval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisionRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_project_and_creates_client()
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->post(route('projects.store'), [
            'name' => 'Test Video Project',
            'description' => 'My cool description',
            'client_name' => 'John Client',
            'client_email' => 'client@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Video Project',
            'editor_id' => $editor->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'role' => 'client',
        ]);
    }

    public function test_client_can_access_project_via_magic_link()
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $client = User::factory()->create(['role' => 'client']);
        $project = Project::create([
            'name' => 'Promo Video',
            'editor_id' => $editor->id,
            'client_id' => $client->id,
            'share_token' => 'securetoken123',
        ]);

        $response = $this->get(route('client.projects.login', 'securetoken123'));

        $response->assertRedirect(route('client.projects.show', 'securetoken123'));
        $this->assertAuthenticatedAs($client);
    }

    public function test_can_add_timestamped_comment_to_draft()
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $project = Project::create([
            'name' => 'Promo Video',
            'editor_id' => $editor->id,
            'share_token' => 'token123',
        ]);
        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => 1,
            'video_path' => 'drafts/video.mp4',
            'original_filename' => 'video.mp4',
            'status' => 'ready',
        ]);

        $response = $this->post(route('comments.store', $draft->id), [
            'content' => 'Fix color at 00:34',
            'timestamp_seconds' => 34.5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'draft_id' => $draft->id,
            'content' => 'Fix color at 00:34',
            'timestamp_seconds' => 34.5,
        ]);
    }

    public function test_editor_can_resolve_comment()
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $project = Project::create([
            'name' => 'Promo Video',
            'editor_id' => $editor->id,
            'share_token' => 'token123',
        ]);
        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => 1,
            'video_path' => 'drafts/video.mp4',
            'original_filename' => 'video.mp4',
            'status' => 'ready',
        ]);
        $comment = Comment::create([
            'draft_id' => $draft->id,
            'content' => 'Cut this',
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($editor)->post(route('comments.resolve', $comment->id), [
            'is_resolved' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'is_resolved' => true,
            'resolved_by' => $editor->id,
        ]);
    }

    public function test_client_can_approve_draft()
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $client = User::factory()->create(['role' => 'client']);
        $project = Project::create([
            'name' => 'Promo Video',
            'editor_id' => $editor->id,
            'client_id' => $client->id,
            'share_token' => 'token123',
        ]);
        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => 1,
            'video_path' => 'drafts/video.mp4',
            'original_filename' => 'video.mp4',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($client)->post(route('approvals.store', $draft->id), [
            'approver_name' => 'John Client',
            'remarks' => 'Amazing work!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('approvals', [
            'project_id' => $project->id,
            'draft_id' => $draft->id,
            'approver_name' => 'John Client',
            'remarks' => 'Amazing work!',
        ]);

        $this->assertEquals('approved', $project->refresh()->status);
    }
}
