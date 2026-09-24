<?php

namespace App\Infrastructure;

use App\Contracts\PrivateProofStorageInterface;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LaravelPrivateProofStorage implements PrivateProofStorageInterface
{
    public function store(string $directory, UploadedFile $file): array
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported proof MIME type.'),
        };
        $disk = 'local';
        $key = trim($directory, '/').'/'.Str::uuid().'.'.$extension;
        Storage::disk($disk)->putFileAs(dirname($key), $file, basename($key));

        return ['disk' => $disk, 'key' => $key, 'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize()];
    }

    public function delete(string $disk, string $key): void
    {
        Storage::disk($disk)->delete($key);
    }

    public function temporaryUrl(string $disk, string $key, ?DateTimeInterface $expiresAt = null): string
    {
        return Storage::disk($disk)->temporaryUrl($key, $expiresAt ?? now()->addMinutes(5));
    }
}
