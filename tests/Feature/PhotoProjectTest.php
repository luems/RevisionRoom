<?php

use App\Models\User;
use App\Models\Project;
use App\Models\Draft;
use App\Models\DraftItem;
use App\Models\Comment;
use App\Models\Approval;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
});

test('editor can create a photo project', function () {
    $editor = User::factory()->create();

    $response = $this->actingAs($editor)->post(route('projects.store'), [
        'name' => 'Fashion Studio Campaign',
        'description' => 'High-resolution editorial photography review',
        'media_type' => 'photo',
        'client_name' => 'Vogue Studio',
        'client_email' => 'editor@vogue.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('projects', [
        'name' => 'Fashion Studio Campaign',
        'media_type' => 'photo',
        'editor_id' => $editor->id,
    ]);
});

test('editor can upload single and multi-photo drafts to photo project', function () {
    $editor = User::factory()->create();
    $project = Project::create([
        'editor_id' => $editor->id,
        'name' => 'Lookbook 2026',
        'media_type' => 'photo',
        'status' => 'active',
        'share_token' => Str::random(32),
    ]);

    $file1 = UploadedFile::fake()->create('look1.jpg', 100, 'image/jpeg');
    $file2 = UploadedFile::fake()->create('look2.png', 150, 'image/png');

    $response = $this->actingAs($editor)->post(route('drafts.store', $project->id), [
        'photos' => [$file1, $file2],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('drafts', [
        'project_id' => $project->id,
        'version_number' => 1,
    ]);

    $draft = Draft::where('project_id', $project->id)->first();
    expect($draft->items)->toHaveCount(2);

    $this->assertDatabaseHas('draft_items', [
        'draft_id' => $draft->id,
        'original_filename' => 'look1.jpg',
    ]);
});

test('client can view photo draft in client portal and stream photo media securely', function () {
    $editor = User::factory()->create();
    $project = Project::create([
        'editor_id' => $editor->id,
        'name' => 'Editorial Shoot',
        'media_type' => 'photo',
        'status' => 'active',
        'share_token' => Str::random(32),
    ]);

    $draft = Draft::create([
        'project_id' => $project->id,
        'version_number' => 1,
        'original_filename' => 'editorial.jpg',
        'video_path' => 'dummy.jpg',
        'file_path' => 'dummy.jpg',
        'status' => 'ready',
    ]);

    $file = UploadedFile::fake()->create('editorial.jpg', 100, 'image/jpeg');
    $path = $file->store("projects/{$project->id}/drafts/{$draft->id}", 'public');

    $item = DraftItem::create([
        'draft_id' => $draft->id,
        'file_path' => $path,
        'thumbnail_path' => $path,
        'original_filename' => 'editorial.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 102400,
        'width' => 1200,
        'height' => 800,
    ]);

    // 1. Visit client review page to establish session token
    $portalResponse = $this->get(route('client.projects.show', $project->share_token));
    $portalResponse->assertStatus(200);

    // 2. Stream photo media via secure route
    $streamResponse = $this->get(route('draft_items.media', ['draftItem' => $item->id, 'share_token' => $project->share_token]));
    $streamResponse->assertStatus(200);
    $streamResponse->assertHeader('content-type', 'image/jpeg');
});

test('client can leave pinned comment on photo draft', function () {
    $editor = User::factory()->create();
    $project = Project::create([
        'editor_id' => $editor->id,
        'name' => 'Model Shoot',
        'media_type' => 'photo',
        'status' => 'active',
        'share_token' => Str::random(32),
    ]);

    $draft = Draft::create([
        'project_id' => $project->id,
        'version_number' => 1,
        'original_filename' => 'photo.jpg',
        'video_path' => 'dummy.jpg',
        'file_path' => 'dummy.jpg',
        'status' => 'ready',
    ]);

    $item = DraftItem::create([
        'draft_id' => $draft->id,
        'file_path' => 'dummy.jpg',
        'thumbnail_path' => 'dummy_thumb.jpg',
        'original_filename' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 1000,
        'width' => 800,
        'height' => 600,
    ]);

    $response = $this->post(route('comments.store', $draft->id), [
        'content' => 'Please remove saturation on model jacket',
        'author_name' => 'Client Reviewer',
        'position_x' => 45.5,
        'position_y' => 62.3,
        'draft_item_id' => $item->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('comments', [
        'draft_id' => $draft->id,
        'draft_item_id' => $item->id,
        'content' => 'Please remove saturation on model jacket',
        'position_x' => 45.5,
        'position_y' => 62.3,
    ]);
});

test('pdf approval record generates successfully for photo project', function () {
    $editor = User::factory()->create();
    $project = Project::create([
        'editor_id' => $editor->id,
        'name' => 'Catalog Shoot',
        'media_type' => 'photo',
        'status' => 'approved',
        'share_token' => Str::random(32),
    ]);

    $draft = Draft::create([
        'project_id' => $project->id,
        'version_number' => 1,
        'original_filename' => 'catalog1.jpg',
        'video_path' => 'catalog1.jpg',
        'file_path' => 'catalog1.jpg',
        'status' => 'ready',
    ]);

    DraftItem::create([
        'draft_id' => $draft->id,
        'file_path' => 'catalog1.jpg',
        'thumbnail_path' => 'catalog1_thumb.jpg',
        'original_filename' => 'catalog1.jpg',
        'mime_type' => 'image/jpeg',
        'file_size' => 200000,
        'width' => 2000,
        'height' => 1500,
    ]);

    Approval::create([
        'project_id' => $project->id,
        'draft_id' => $draft->id,
        'approver_name' => 'Brand Manager',
        'remarks' => 'Approved for print publication',
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($editor)->get(route('projects.download-record', $project->id));
    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});
