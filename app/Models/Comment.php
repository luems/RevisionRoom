<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['draft_id', 'user_id', 'author_name', 'content', 'timestamp_seconds', 'is_resolved', 'resolved_at', 'resolved_by', 'is_rejected', 'rejection_reason'])]
class Comment extends Model
{
    protected $casts = [
        'is_resolved' => 'boolean',
        'is_rejected' => 'boolean',
        'resolved_at' => 'datetime',
        'timestamp_seconds' => 'float',
    ];

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
