<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Draft;
use App\Jobs\ProcessVideoDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

    public function uploadChunk(Request $request, $projectId)
    {
        $project = Project::where('editor_id', Auth::id())->findOrFail($projectId);

        $request->validate([
            'file' => 'required|file',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'filename' => 'required|string',
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $filename = $request->input('filename');
        $uploadId = $request->input('upload_id');

        abort_if($chunkIndex >= $totalChunks, 422, 'Invalid chunk index.');

        $chunkFile = $request->file('file');
        
        // Define directory to store temporary chunks
        $tempDir = storage_path("app/chunks/{$uploadId}");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // The browser sends chunks sequentially. Append each chunk immediately so
        // the final request does not have to copy the entire video again at 99%.
        $assemblyPath = "{$tempDir}/video.part";
        $nextChunkPath = "{$tempDir}/next_chunk";
        $nextChunk = file_exists($nextChunkPath) ? (int) file_get_contents($nextChunkPath) : 0;

        if ($chunkIndex !== $nextChunk) {
            return response()->json([
                'message' => "Unexpected chunk {$chunkIndex}; expected {$nextChunk}.",
            ], 409);
        }

        $input = fopen($chunkFile->getRealPath(), 'rb');
        $output = fopen($assemblyPath, $chunkIndex === 0 ? 'wb' : 'ab');

        if ($input === false || $output === false) {
            throw new \RuntimeException('Unable to open the upload assembly file.');
        }

        try {
            stream_copy_to_stream($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }

        file_put_contents($nextChunkPath, (string) ($chunkIndex + 1), LOCK_EX);

        if ($chunkIndex === $totalChunks - 1) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
            $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
            $finalPath = "drafts/{$newFilename}";

            if ($diskName === 'public') {
                $finalPhysicalPath = Storage::disk($diskName)->path($finalPath);
                
                // Ensure directory exists
                $finalDir = dirname($finalPhysicalPath);
                if (!file_exists($finalDir)) {
                    mkdir($finalDir, 0777, true);
                }

                // Same filesystem: rename is atomic and effectively instant,
                // regardless of the completed video's size.
                if (!rename($assemblyPath, $finalPhysicalPath)) {
                    throw new \RuntimeException('Unable to finalize the uploaded video.');
                }
            } else {
                $fileContent = fopen($assemblyPath, 'rb');
                Storage::disk($diskName)->put($finalPath, $fileContent);
                fclose($fileContent);
                unlink($assemblyPath);
            }

            @unlink($nextChunkPath);
            @rmdir($tempDir);

            // Get next version number
            $nextVersion = $project->drafts()->max('version_number') + 1;

            $draft = Draft::create([
                'project_id' => $project->id,
                'version_number' => $nextVersion,
                'video_path' => $finalPath,
                'thumbnail_path' => null,
                'duration' => null,
                'original_filename' => $filename,
                'status' => 'processing',
            ]);

            // Dispatch background processing job
            ProcessVideoDraft::dispatch($draft);

            return response()->json([
                'status' => 'completed',
                'version' => $nextVersion,
                'message' => 'Video draft uploaded and merged successfully.'
            ]);
        }

        return response()->json([
            'status' => 'chunk_uploaded',
            'chunk_index' => $chunkIndex,
            'message' => 'Chunk uploaded successfully.'
        ]);
    }
}
