<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'draft_id',
    'file_path',
    'thumbnail_path',
    'original_filename',
    'mime_type',
    'file_size',
    'width',
    'height',
    'sort_order',
])]
class DraftItem extends Model
{
    protected $appends = ['file_url', 'thumbnail_url'];

    public function draft()
    {
        return $this->belongsTo(Draft::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'asc');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) return null;

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($this->file_path);
        }
        return route('draft_items.media', $this->id);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) return $this->file_url;

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($this->thumbnail_path);
        }
        return route('draft_items.media', ['draftItem' => $this->id, 'type' => 'thumbnail']);
    }
}
