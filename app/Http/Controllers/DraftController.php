<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Draft;
use App\Jobs\ProcessVideoDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DraftController extends Controller
{
    public function store(Request $request, $projectId)
    {
        $project = Project::where('editor_id', Auth::id())->findOrFail($projectId);

        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,mkv,webm|max:2097152', // 2GB max
        ]);

        $file = $request->file('video');
        $originalFilename = $file->getClientOriginalName();

        // Save file to storage
        $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
        $videoPath = Storage::disk($diskName)->putFile('drafts', $file);

        // Get next version number
        $nextVersion = $project->drafts()->max('version_number') + 1;

        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => $nextVersion,
            'video_path' => $videoPath,
            'thumbnail_path' => null,
            'duration' => null,
            'original_filename' => $originalFilename,
            'status' => 'processing',
        ]);

        // Dispatch background processing job
        ProcessVideoDraft::dispatch($draft);

        return redirect()->back()->with('success', 'Draft uploaded and processing.');
    }

    public function stream($id)
    {
        $draft = Draft::findOrFail($id);
        $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';

        if ($diskName === 's3') {
            return redirect(Storage::disk('s3')->url($draft->video_path));
        }

        $path = Storage::disk('public')->path($draft->video_path);

        if (!file_exists($path)) {
            abort(404);
        }

        $size = filesize($path);
        $file = fopen($path, 'rb');
        $start = 0;
        $length = $size;
        $status = 200;

        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
        ];

        if (request()->headers->has('Range')) {
            $range = request()->header('Range');
            $range = str_replace('bytes=', '', $range);
            $range = explode('-', $range);
            $start = (int) $range[0];
            if (isset($range[1]) && is_numeric($range[1])) {
                $end = (int) $range[1];
            } else {
                $end = $size - 1;
            }
            $length = $end - $start + 1;
            $status = 206;

            $headers['Content-Range'] = "bytes $start-$end/$size";
            $headers['Content-Length'] = $length;
        } else {
            $headers['Content-Length'] = $size;
        }

        return response()->stream(function () use ($file, $start, $length) {
            fseek($file, $start);
            $chunkSize = 1024 * 8; // 8KB chunks
            $bytesSent = 0;
            while (!feof($file) && $bytesSent < $length) {
                $buffer = fread($file, min($chunkSize, $length - $bytesSent));
                echo $buffer;
                flush();
                $bytesSent += strlen($buffer);
            }
            fclose($file);
        }, $status, $headers);
    }
}
