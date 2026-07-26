<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->integer('version_number'); // e.g. 1, 2, 3
            $table->string('video_path');
            $table->string('thumbnail_path')->nullable();
            $table->float('duration')->nullable();
            $table->string('original_filename');
            $table->string('status')->default('processing'); // processing, ready, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
