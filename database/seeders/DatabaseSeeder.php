<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\Draft;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Editor User
        $editor = User::create([
            'name' => 'Jane Editor',
            'email' => 'editor@example.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        // 2. Create Client User
        $client = User::create([
            'name' => 'Alex Client',
            'email' => 'client@example.com',
            'password' => bcrypt('password'),
            'role' => 'client',
        ]);

        // 3. Create Sample Project
        $project = Project::create([
            'name' => 'RevisionRoom Promo Video',
            'description' => 'Drafts and feedback for the upcoming marketing launch promo video.',
            'editor_id' => $editor->id,
            'client_id' => $client->id,
            'share_token' => 'demotoken123',
            'status' => 'active',
        ]);

        // 4. Create Sample Draft (v1)
        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => 1,
            'video_path' => 'drafts/demo.mp4', // placeholder
            'thumbnail_path' => null,
            'duration' => 65.0, // 1m 5s
            'original_filename' => 'Promo_Draft_v1.mp4',
            'status' => 'ready',
        ]);

        // 5. Create Sample Comments
        Comment::create([
            'draft_id' => $draft->id,
            'user_id' => $client->id,
            'author_name' => 'Alex Client',
            'content' => 'Please fix the transition pacing at the beginning.',
            'timestamp_seconds' => 5.2,
            'is_resolved' => false,
        ]);

        Comment::create([
            'draft_id' => $draft->id,
            'user_id' => $client->id,
            'author_name' => 'Alex Client',
            'content' => 'The background music volume is too high in this segment.',
            'timestamp_seconds' => 32.5,
            'is_resolved' => false,
        ]);

        Comment::create([
            'draft_id' => $draft->id,
            'user_id' => $editor->id,
            'author_name' => 'Jane Editor',
            'content' => 'Added temporary placeholder text here.',
            'timestamp_seconds' => null, // general comment
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $editor->id,
        ]);
    }
}
