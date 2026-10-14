<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RestaurantProfileImageManager
{
    public function store(UploadedFile $image): string
    {
        $path = $image->store('restaurants', 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The restaurant profile image could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return;
        }

        if (! Storage::disk('public')->delete($path)) {
            throw new RuntimeException('The previous restaurant profile image could not be deleted.');
        }
    }
}
