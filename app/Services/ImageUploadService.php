<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class ImageUploadService
{
    /**
     * Convert an uploaded image to WebP and save it.
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param int $quality
     * @return string Publicly accessible path (starts with /storage/)
     * @throws \Exception
     */
    public static function uploadAndConvertToWebp(UploadedFile $file, string $directory, int $quality = 85): string
    {
        $imagePath = $file->getRealPath();
        $info = @getimagesize($imagePath);
        if (!$info) {
            throw new \Exception('Invalid image file');
        }

        $mime = $info['mime'];
        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($imagePath),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($imagePath),
            'image/gif' => @imagecreatefromgif($imagePath),
            'image/webp' => @imagecreatefromwebp($imagePath),
            default => null,
        };

        if (!$image) {
            throw new \Exception('Failed to read image. Make sure the file format is valid.');
        }

        // Keep transparency for PNG/WebP
        imagealphablending($image, false);
        imagesavealpha($image, true);

        // Generate unique filename
        $filename = uniqid() . '.webp';

        // Save locally to a temporary file in WebP format
        $tempPath = tempnam(sys_get_temp_dir(), 'webp');
        if (!@imagewebp($image, $tempPath, $quality)) {
            imagedestroy($image);
            @unlink($tempPath);
            throw new \Exception('Failed to convert image to WebP.');
        }

        imagedestroy($image);

        // Upload to public disk
        $storedPath = Storage::disk('public')->putFileAs($directory, new File($tempPath), $filename);
        @unlink($tempPath);

        if (!$storedPath) {
            throw new \Exception('Failed to store file in public disk.');
        }

        return '/storage/' . $storedPath;
    }
}
