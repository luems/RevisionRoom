<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['draft_id', 'user_id', 'author_name', 'content', 'timestamp_seconds', 'is_resolved', 'resolved_at', 'resolved_by', 'is_rejected', 'rejection_reason', 'image_path'])]
class Comment extends Model
{
    protected $casts = [
        'is_resolved' => 'boolean',
        'is_rejected' => 'boolean',
        'resolved_at' => 'datetime',
        'timestamp_seconds' => 'float',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) return null;
        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($this->image_path);
        }
        return Storage::url($this->image_path);
    }

    public function draft()
    {
        return $this->belongsTo(Draft::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
