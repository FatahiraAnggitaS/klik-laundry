<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface PrivateProofStorageInterface
{
    /** @return array{disk: string, key: string, mime: string, size: int} */
    public function store(string $directory, UploadedFile $file): array;

    public function delete(string $disk, string $key): void;

    public function temporaryUrl(string $disk, string $key, int $minutes = 5): string;
}
