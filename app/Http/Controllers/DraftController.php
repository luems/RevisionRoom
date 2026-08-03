<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Draft;
use App\Models\DraftItem;
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

        if ($project->isPhoto()) {
            return $this->storePhotoDraft($request, $project);
        }

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

    protected function storePhotoDraft(Request $request, Project $project)
    {
        $request->validate([
            'photos' => 'required_without:photo|array|min:1|max:20',
            'photos.*' => 'file|image|mimes:jpeg,jpg,png,webp,avif|max:51200', // 50MB per photo
            'photo' => 'nullable|file|image|mimes:jpeg,jpg,png,webp,avif|max:51200',
        ]);

        $files = $request->file('photos') ?? [$request->file('photo')];
        $files = array_filter($files);

        if (empty($files)) {
            return redirect()->back()->withErrors(['photos' => 'At least one photo is required.']);
        }

        $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
        $nextVersion = $project->drafts()->max('version_number') + 1;

        $draft = Draft::create([
            'project_id' => $project->id,
            'version_number' => $nextVersion,
            'video_path' => '',
            'thumbnail_path' => null,
            'duration' => null,
            'original_filename' => $files[0]->getClientOriginalName(),
            'status' => 'ready',
        ]);

        foreach ($files as $index => $file) {
            $originalFilename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType() ?? $file->getClientMimeType();
            $fileSize = $file->getSize();

            // Verify image dimensions
            $dimensions = @getimagesize($file->getRealPath());
            $width = $dimensions ? $dimensions[0] : null;
            $height = $dimensions ? $dimensions[1] : null;

            // Generate unique filename
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $randomName = (string) Str::uuid() . '.' . $extension;
            $photoPath = "drafts/photos/{$randomName}";
            $thumbPath = "drafts/photos/thumb_{$randomName}";

            // Store original file
            Storage::disk($diskName)->putFileAs('drafts/photos', $file, $randomName);

            // Generate thumbnail & correct orientation if possible
            $this->createPhotoThumbnail($file->getRealPath(), Storage::disk($diskName)->path($thumbPath), $mimeType);

            $draftItem = DraftItem::create([
                'draft_id' => $draft->id,
                'file_path' => $photoPath,
                'thumbnail_path' => $thumbPath,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'sort_order' => $index,
            ]);

            if ($index === 0) {
                $draft->update([
                    'thumbnail_path' => $thumbPath,
                    'video_path' => $photoPath,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Photo draft uploaded successfully.');
    }

    protected function createPhotoThumbnail(string $sourcePath, string $targetPath, string $mimeType)
    {
        try {
            $targetDir = dirname($targetPath);
            if (!file_exists($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }

            if (!function_exists('imagecreatefromstring')) {
                @copy($sourcePath, $targetPath);
                return;
            }

            $imgData = @file_get_contents($sourcePath);
            if (!$imgData) {
                @copy($sourcePath, $targetPath);
                return;
            }

            $srcImg = @imagecreatefromstring($imgData);
            if (!$srcImg) {
                @copy($sourcePath, $targetPath);
                return;
            }

            // Correct EXIF orientation for JPEG
            if (function_exists('exif_read_data') && ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg')) {
                $exif = @exif_read_data($sourcePath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $srcImg = imagerotate($srcImg, 180, 0);
                            break;
                        case 6:
                            $srcImg = imagerotate($srcImg, -90, 0);
                            break;
                        case 8:
                            $srcImg = imagerotate($srcImg, 90, 0);
                            break;
                    }
                }
            }

            $origW = imagesx($srcImg);
            $origH = imagesy($srcImg);
            $maxDim = 400;

            if ($origW > $maxDim || $origH > $maxDim) {
                if ($origW >= $origH) {
                    $newW = $maxDim;
                    $newH = (int) round(($origH / $origW) * $maxDim);
                } else {
                    $newH = $maxDim;
                    $newW = (int) round(($origW / $origH) * $maxDim);
                }
            } else {
                $newW = $origW;
                $newH = $origH;
            }

            $thumbImg = imagecreatetruecolor($newW, $newH);

            // Handle transparency for PNG/WebP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($thumbImg, false);
                imagesavealpha($thumbImg, true);
            }

            imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            if ($mimeType === 'image/png') {
                imagepng($thumbImg, $targetPath, 7);
            } elseif ($mimeType === 'image/webp' && function_exists('imagewebp')) {
                imagewebp($thumbImg, $targetPath, 80);
            } else {
                imagejpeg($thumbImg, $targetPath, 85);
            }

            imagedestroy($srcImg);
            imagedestroy($thumbImg);
        } catch (\Throwable $e) {
            @copy($sourcePath, $targetPath);
        }
    }

    public function streamItem(Request $request, $draftItemId)
    {
        $draftItem = DraftItem::with('draft.project')->findOrFail($draftItemId);
        $project = $draftItem->draft->project;

        // Authorization: Editor owns project OR valid client share token
        $isAuthorized = false;
        if (Auth::check() && Auth::id() === $project->editor_id) {
            $isAuthorized = true;
        } elseif ($request->has('share_token') && $request->input('share_token') === $project->share_token) {
            $isAuthorized = true;
        } elseif (session("client_authenticated_{$project->share_token}")) {
            $isAuthorized = true;
        }

        if (!$isAuthorized) {
            abort(403, 'Unauthorized access to media.');
        }

        $type = $request->input('type') === 'thumbnail' ? 'thumbnail' : 'file';
        $pathKey = $type === 'thumbnail' ? ($draftItem->thumbnail_path ?? $draftItem->file_path) : $draftItem->file_path;

        $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
        if ($diskName === 's3') {
            return redirect(Storage::disk('s3')->url($pathKey));
        }

        $fullPath = Storage::disk('public')->path($pathKey);
        if (!file_exists($fullPath)) {
            abort(404, 'Media file not found.');
        }

        return response()->file($fullPath, [
            'Content-Type' => $draftItem->mime_type ?? 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
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
        
        $tempDir = storage_path("app/chunks/{$uploadId}");
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

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
                
                $finalDir = dirname($finalPhysicalPath);
                if (!file_exists($finalDir)) {
                    mkdir($finalDir, 0777, true);
                }

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
