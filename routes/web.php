<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\DraftController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ClientPortalController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

// Map standard Dashboard to ProjectController index
Route::get('/dashboard', [ProjectController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Auth routes for Editor
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Projects & Drafts CRUD
    Route::resource('projects', ProjectController::class)->except(['index']);
    Route::post('projects/{project}/drafts', [DraftController::class, 'store'])->name('drafts.store');
    Route::post('projects/{project}/drafts/upload-chunk', [DraftController::class, 'uploadChunk'])->name('drafts.upload-chunk');
    Route::get('projects/{project}/compare', [ProjectController::class, 'compare'])->name('projects.compare');
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
});

// Client Portal & Shared routes (no password auth or magic auth)
Route::get('review/{share_token}', [ClientPortalController::class, 'loginWithToken'])->name('client.projects.login');
Route::get('review/{share_token}/portal', [ClientPortalController::class, 'showProject'])->name('client.projects.show');

// Comments & Approvals (Accessible by client and editor)
Route::post('drafts/{draft}/comments', [CommentController::class, 'store'])->name('comments.store');
Route::post('comments/{comment}/resolve', [CommentController::class, 'resolve'])->name('comments.resolve');
Route::post('comments/{comment}/reject', [CommentController::class, 'reject'])->name('comments.reject');
Route::post('drafts/{draft}/approve', [ApprovalController::class, 'store'])->name('approvals.store');
Route::delete('projects/{project}/cancel-approval', [ApprovalController::class, 'destroy'])->name('approvals.cancel');
Route::get('projects/{project}/download-record', [ApprovalController::class, 'downloadRecord'])->name('projects.download-record');
Route::get('drafts/{draft}/stream', [DraftController::class, 'stream'])->name('drafts.stream');

require __DIR__.'/auth.php';
