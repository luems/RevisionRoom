<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['project_id', 'version_number', 'video_path', 'thumbnail_path', 'duration', 'original_filename', 'status'])]
class Draft extends Model
{
    protected $appends = ['video_url', 'thumbnail_url'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('timestamp_seconds', 'asc')->orderBy('created_at', 'asc');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function getVideoUrlAttribute(): ?string
    {
        if (!$this->video_path) return null;

        // Handle S3/MinIO vs local storage
        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($this->video_path);
        }
        return route('drafts.stream', $this->id);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) return null;

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($this->thumbnail_path);
        }
        return Storage::url($this->thumbnail_path);
    }
}
