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

    public function uploadChunk(Request $request, $projectId)
    {
        $project = Project::where('editor_id', Auth::id())->findOrFail($projectId);

        $request->validate([
            'file' => 'required|file',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'filename' => 'required|string',
            'upload_id' => 'required|string',
        ]);

        $chunkIndex = $request->input('chunk_index');
        $totalChunks = $request->input('total_chunks');
        $filename = $request->input('filename');
        $uploadId = $request->input('upload_id');

        $chunkFile = $request->file('file');
        
        // Define directory to store temporary chunks
        $tempDir = storage_path("app/chunks/{$uploadId}");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Move the chunk file
        $chunkFile->move($tempDir, "chunk_{$chunkIndex}");

        // Check if all chunks have been uploaded
        $uploadedChunks = count(glob("{$tempDir}/chunk_*"));

        if ($uploadedChunks === $totalChunks) {
            // Merge all chunks
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $newFilename = md5($filename . time()) . '.' . $extension;
            $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
            $finalPath = "drafts/{$newFilename}";

            if ($diskName === 'public' || $diskName === 'local') {
                $finalPhysicalPath = Storage::disk($diskName)->path($finalPath);
                
                // Ensure directory exists
                $finalDir = dirname($finalPhysicalPath);
                if (!file_exists($finalDir)) {
                    mkdir($finalDir, 0777, true);
                }

                $out = fopen($finalPhysicalPath, "wb");
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = "{$tempDir}/chunk_{$i}";
                    $in = fopen($chunkPath, "rb");
                    while ($buff = fread($in, 262144)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    @unlink($chunkPath); // Delete chunk after merging
                }
                fclose($out);
                @rmdir($tempDir);
            } else {
                // If using S3 or another cloud disk, merge to temp file then put to S3
                $tempMergedFile = storage_path("app/chunks/{$uploadId}_merged.tmp");
                $out = fopen($tempMergedFile, "wb");
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = "{$tempDir}/chunk_{$i}";
                    $in = fopen($chunkPath, "rb");
                    while ($buff = fread($in, 262144)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    @unlink($chunkPath);
                }
                fclose($out);
                @rmdir($tempDir);

                $fileContent = fopen($tempMergedFile, 'r');
                Storage::disk($diskName)->put($finalPath, $fileContent);
                fclose($fileContent);
                @unlink($tempMergedFile);
            }

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
