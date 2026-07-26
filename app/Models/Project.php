<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'description', 'editor_id', 'client_id', 'share_token', 'status'])]
class Project extends Model
{
    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function drafts()
    {
        return $this->hasMany(Draft::class)->orderBy('version_number', 'desc');
    }

    public function latestDraft()
    {
        return $this->hasOne(Draft::class)->latestOfMany();
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }
}
