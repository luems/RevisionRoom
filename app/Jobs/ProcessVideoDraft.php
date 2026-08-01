<?php

namespace App\Jobs;

use App\Models\Draft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Http\File;

class ProcessVideoDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $draft;

    public function __construct(Draft $draft)
    {
        $this->draft = $draft;
    }

    public function handle()
    {
        $draft = $this->draft;
        $draft->status = 'processing';
        $draft->save();

        try {
            $diskName = config('filesystems.default') === 's3' ? 's3' : 'public';
            $disk = Storage::disk($diskName);

            // Create temp folder
            $tempDir = storage_path('app/temp_videos');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Get local path for processing
            $localVideoPath = '';
            if (config('filesystems.default') === 's3') {
                $localVideoPath = $tempDir . '/' . Str::random(40) . '.' . pathinfo($draft->original_filename, PATHINFO_EXTENSION);
                file_put_contents($localVideoPath, Storage::disk('s3')->get($draft->video_path));
            } else {
                $localVideoPath = Storage::disk('public')->path($draft->video_path);
            }

            if (!file_exists($localVideoPath) || filesize($localVideoPath) === 0) {
                throw new \Exception("Video file does not exist locally: " . $localVideoPath);
            }

            // 1. Get Duration via direct ffprobe (much faster than PHP wrapper)
            $duration = 0.0;
            try {
                $cmdProbe = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($localVideoPath);
                $shellDuration = shell_exec($cmdProbe);
                if ($shellDuration && is_numeric(trim($shellDuration))) {
                    $duration = (float) trim($shellDuration);
                } else {
                    $duration = 10.0; // Default fallback
                }
            } catch (\Exception $e) {
                Log::warning("FFProbe direct execution failed: " . $e->getMessage());
                $duration = 10.0;
            }

            // 2. Extract Thumbnail via high-performance fast-seek ffmpeg (-ss before -i is instant)
            $localThumbName = Str::random(40) . '.jpg';
            $localThumbPath = $tempDir . '/' . $localThumbName;
            $thumbSec = $duration > 2.0 ? 2.0 : $duration / 2.0;

            try {
                $cmdThumb = "ffmpeg -y -ss " . $thumbSec . " -i " . escapeshellarg($localVideoPath) . " -vframes 1 " . escapeshellarg($localThumbPath) . " 2>&1";
                shell_exec($cmdThumb);
            } catch (\Exception $e) {
                Log::warning("FFMpeg direct command failed: " . $e->getMessage());
            }

            // 3. Move thumbnail to storage
            $thumbnailPathDb = null;
            if (file_exists($localThumbPath) && filesize($localThumbPath) > 0) {
                $thumbnailPathDb = $disk->putFile('thumbnails', new File($localThumbPath));
                unlink($localThumbPath);
            } else {
                Log::error("Thumbnail generation failed.");
            }

            // Clean up S3 temporary video file
            if (config('filesystems.default') === 's3' && file_exists($localVideoPath)) {
                unlink($localVideoPath);
            }

            // Update database
            $draft->duration = $duration;
            $draft->thumbnail_path = $thumbnailPathDb;
            $draft->status = 'ready';
            $draft->save();

            Log::info("Draft processed successfully: " . $draft->id);

        } catch (\Exception $e) {
            Log::error("Error processing draft: " . $draft->id . ". Msg: " . $e->getMessage());
            $draft->status = 'failed';
            $draft->save();
        }
    }
}
