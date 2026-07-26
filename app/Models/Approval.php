<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['project_id', 'draft_id', 'user_id', 'approver_name', 'remarks', 'ip_address', 'user_agent'])]
class Approval extends Model
{
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function draft()
    {
        return $this->belongsTo(Draft::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
