<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

trait HandlesImageUploads
{
    /**
     * Determines whether to handle an uploaded file or a captured base64 photo,
     * then saves original and creates a web-optimized version.
     * This method acts as a unified entry point for photo processing.
     *
     * @param Request $request The incoming HTTP request.
     * @param string $category The image category ('profile' or 'passport').
     * @param string $fileInputName The name of the file input field (e.g., 'picture').
     * @param string $capturedPhotoInputName The name of the captured photo input field (e.g., 'captured_photo').
     * @return string|null The base filename on success, null on failure.
     */
    protected function processPhotoUpload(
        Request $request,
        string  $category,
        string  $fileInputName = 'picture',
        string  $capturedPhotoInputName = 'captured_photo'
    ): ?string
    {
        // Handle captured photo (base64)
        if ($request->filled($capturedPhotoInputName) && str_starts_with($request->input($capturedPhotoInputName), 'data:image')) {
            return $this->handleCapturedBase64Photo($request->input($capturedPhotoInputName), $category);
        }

        // Handle file upload
        if ($request->hasFile($fileInputName)) {
            return $this->handleUploadedFile($request->file($fileInputName), $category);
        }

        return null;
    }

    /**
     * Handles file upload, saves original, and creates a web-optimized version.
     *
     * @param UploadedFile $file The uploaded file instance.
     * @param string $category 'profile' or 'passport'
     * @param string $prefix Prefix for the filename (e.g., 'upload_', 'captured_').
     * @return string|null The base filename on success, null on failure.
     */
    protected function handleUploadedFile(UploadedFile $file, string $category, string $prefix = 'upload_'): ?string
    {
        try {
            if (!$file->isValid()) {
                return null;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $baseFilename = $prefix . time() . '_' . uniqid() . '.' . $extension;

            $originalDir = 'photos/' . $category . '/original';
            $webDir = 'photos/' . $category . '/web';

            // Ensure directories exist under storage/app/
            Storage::disk('local')->makeDirectory($originalDir);
            Storage::disk('local')->makeDirectory($webDir);

            $originalFullPath = Storage::disk('local')->path($originalDir . '/' . $baseFilename);
            $webFullPath      = Storage::disk('local')->path($webDir . '/' . $baseFilename);

            // 1. Move the uploaded file to the 'original' location
            if (!$file->move(Storage::disk('local')->path($originalDir), $baseFilename)) {
                throw new \Exception('Failed to move uploaded file to original location');
            }

            // Determine optimization parameters based on file type
            $maxWidthWeb = 400;
            $maxHeightWeb = 400;
            $qualityWeb = 95; // High quality for JPEGs, max compression for PNGs

            if ($extension === 'png') {
                // More aggressive dimension reduction for PNGs to meet the 50KB target
                $maxWidthWeb = 200;
                $maxHeightWeb = 200;
            }


            $optimized = $this->createOptimizedImage(
                $originalFullPath,
                $webFullPath,
                $maxWidthWeb,
                $maxHeightWeb,
                $qualityWeb
            );

            if (!$optimized) {
                Log::error('Photo upload rejected: web-optimized image could not be generated. Discarding upload rather than recording a filename PhotoController cannot serve.', [
                    'category' => $category,
                    'filename' => $baseFilename,
                    'original_client_name' => $file->getClientOriginalName(),
                    'actor_id' => Auth::id(),
                ]);

                // Don't leave an orphaned original/ file with no usable web/ copy and no DB reference.
                Storage::disk('local')->delete($originalDir . '/' . $baseFilename);

                return null;
            }

            return $baseFilename;
        } catch (\Exception $e) {
            Log::error('Failed to handle uploaded file for ' . $category, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'actor_id' => Auth::id(),
            ]);
            return null;
        }
    }

    /**
     * Handles base64 captured photo, saves original, and creates a web-optimized version.
     *
     * @param string $base64Data The base64 encoded image string.
     * @param string $category 'profile' or 'passport'
     * @param string $prefix Prefix for the filename (e.g., 'captured_').
     * @return string|null The base filename on success, null on failure.
     */
    protected function handleCapturedBase64Photo(string $base64Data, string $category, string $prefix = 'captured_'): ?string
    {
        try {
            // Extract the base64 data and image type
            list($type, $data) = explode(';', $base64Data);
            list(, $data) = explode(',', $data);

            // Get image extension (assuming common types)
            $extension = 'jpg'; // Default to jpg
            if (str_contains($type, 'png')) {
                $extension = 'png';
            } elseif (str_contains($type, 'gif')) {
                $extension = 'gif';
            }

            $baseFilename = $prefix . time() . '_' . uniqid() . '.' . $extension;

            $originalDir = 'photos/' . $category . '/original';
            $webDir = 'photos/' . $category . '/web';

            // Ensure directories exist under storage/app/
            Storage::disk('local')->makeDirectory($originalDir);
            Storage::disk('local')->makeDirectory($webDir);

            $originalFullPath = Storage::disk('local')->path($originalDir . '/' . $baseFilename);
            $webFullPath      = Storage::disk('local')->path($webDir . '/' . $baseFilename);

            // Decode and save the original image
            $imageData = base64_decode($data);
            if ($imageData === false) {
                throw new \Exception('Failed to decode base64 image data');
            }
            if (file_put_contents($originalFullPath, $imageData) === false) {
                throw new \Exception('Failed to save captured original photo');
            }

            // Determine optimization parameters based on file type
            $maxWidthWeb = 400;
            $maxHeightWeb = 400;
            $qualityWeb = 95; // High quality for JPEGs, max compression for PNGs

            if ($extension === 'png') {
                // More aggressive dimension reduction for PNGs to meet the 50KB target
                $maxWidthWeb = 200;
                $maxHeightWeb = 200;
            }

            $optimized = $this->createOptimizedImage(
                $originalFullPath,
                $webFullPath,
                $maxWidthWeb,
                $maxHeightWeb,
                $qualityWeb
            );

            if (!$optimized) {
                Log::error('Captured photo upload rejected: web-optimized image could not be generated. Discarding upload rather than recording a filename PhotoController cannot serve.', [
                    'category' => $category,
                    'filename' => $baseFilename,
                    'actor_id' => Auth::id(),
                ]);

                // Don't leave an orphaned original/ file with no usable web/ copy and no DB reference.
                Storage::disk('local')->delete($originalDir . '/' . $baseFilename);

                return null;
            }

            return $baseFilename;
        } catch (\Exception $e) {
            Log::error('Failed to handle captured base64 photo for ' . $category, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'actor_id' => Auth::id(),
            ]);
            return null;
        }
    }

    /**
     * Creates an optimized version of an image from a source path to a destination path.
     *
     * @param string $sourcePath The full path to the source image file.
     * @param string $destinationPath The full path where the optimized image will be saved.
     * @param int $maxWidth The maximum width for the optimized image.
     * @param int $maxHeight The maximum height for the optimized image.
     * @param int $quality The quality setting (0-100 for JPEG, 0-9 for PNG compression level mapping).
     * @return bool True on success, false on failure.
     */
    protected function createOptimizedImage(
        string $sourcePath,
        string $destinationPath,
        int    $maxWidth,
        int    $maxHeight,
        int    $quality
    ): bool
    {
        try {
            if (!extension_loaded('gd')) {
                Log::error('Image optimization failed: GD extension not loaded.', [
                    'file' => $sourcePath,
                    'actor_id' => Auth::id(),
                ]);
                return false;
            }

            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                Log::error('Image optimization failed: could not read image dimensions (getimagesize failed).', [
                    'file' => $sourcePath,
                    'actor_id' => Auth::id(),
                ]);
                return false;
            }

            list($originalWidth, $originalHeight, $imageType) = $imageInfo;

            $image = null;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($sourcePath);
                    break;
                default:
                    Log::error('Image optimization failed: unsupported image type.', [
                        'file' => $sourcePath,
                        'type' => $imageType,
                        'actor_id' => Auth::id(),
                    ]);
                    return false;
            }

            if (!$image) {
                Log::error('Image optimization failed: could not create GD image resource from source.', [
                    'file' => $sourcePath,
                    'actor_id' => Auth::id(),
                ]);
                return false;
            }

            // Calculate new dimensions for resizing
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;

            if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
                $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
                $newWidth = (int)($originalWidth * $ratio);
                $newHeight = (int)($originalHeight * $ratio);
            }

            // Create a new true-color image with the calculated dimensions
            $newImage = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

            // Preserve transparency for PNGs and GIFs
            if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                // Fill with transparent background (for PNG/GIF, otherwise it will be black)
                $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                imagefill($newImage, 0, 0, $transparent);
            }

            // Resample/copy the image
            $resampled = imagecopyresampled(
                $newImage,
                $image,
                0, 0, 0, 0,
                (int)$newWidth, (int)$newHeight,
                $originalWidth, $originalHeight
            );

            if (!$resampled) {
                Log::error('Image optimization failed: imagecopyresampled() returned false — resampled canvas may be blank/black rather than the source image. Aborting without writing a destination file.', [
                    'file' => $sourcePath,
                    'destination' => $destinationPath,
                    'actor_id' => Auth::id(),
                ]);

                imagedestroy($image);
                imagedestroy($newImage);

                return false;
            }

            // Save the new image based on its type
            $success = false;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($newImage, $destinationPath, $quality); // quality = 0-100
                    break;
                case IMAGETYPE_PNG:
                    // For imagepng, compression_level is 0-9 (0=no compression, 9=best compression)
                    // We map the 0-100 quality to 0-9 compression directly.
                    // E.g., if quality is 85, (85/100)*9 = 7.65, round to 8.
                    // So, higher quality (JPEG sense) means higher compression level for PNG.
                    $pngCompressionLevel = (int)round(($quality / 100) * 9);
                    $pngCompressionLevel = max(0, min(9, $pngCompressionLevel)); // Ensure 0-9 range
                    $success = imagepng($newImage, $destinationPath, $pngCompressionLevel);
                    break;
                case IMAGETYPE_GIF:
                    $success = imagegif($newImage, $destinationPath); // GIFs are lossless, quality param doesn't apply
                    break;
            }

            imagedestroy($image);
            imagedestroy($newImage);

            if ($success) {
                return true;
            }

            // Guard against a partially-written destination file from a failed encoder write.
            if (file_exists($destinationPath)) {
                @unlink($destinationPath);
            }

            Log::error('Image optimization failed: could not write the final optimized image file.', [
                'file' => $destinationPath,
                'source' => $sourcePath,
                'actor_id' => Auth::id(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Image optimization failed', [
                'file' => $sourcePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'actor_id' => Auth::id(),
            ]);
            return false;
        }
    }

    /**
     * Deletes both original and web-optimized versions of an image given its base filename and category.
     *
     * @param string $baseFilename The base filename (e.g., 'upload_123.png').
     * @param string $category 'profile' or 'passport'
     * @return void
     */
    protected function deleteUserImage(?string $baseFilename, string $category): void
    {
        if (!$baseFilename) {
            return;
        }

        $origRelative = 'photos/' . $category . '/original/' . $baseFilename;
        $webRelative  = 'photos/' . $category . '/web/' . $baseFilename;

        // Remove from new storage location (storage/app/)
        if (Storage::disk('local')->exists($origRelative)) {
            Storage::disk('local')->delete($origRelative);
            Log::info('Deleted original image from storage: ' . $origRelative);
        }
        if (Storage::disk('local')->exists($webRelative)) {
            Storage::disk('local')->delete($webRelative);
            Log::info('Deleted web-optimized image from storage: ' . $webRelative);
        }

        // Backward compat: also remove from old public/ location if present
        $originalPath = public_path($origRelative);
        $webPath      = public_path($webRelative);
        if (File::exists($originalPath)) {
            File::delete($originalPath);
        }
        if (File::exists($webPath)) {
            File::delete($webPath);
        }
    }
}
