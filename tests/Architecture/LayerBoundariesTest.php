<?php

use App\Repositories\Contracts\PlatformSettingRepositoryInterface;
use App\Repositories\Eloquent\EloquentPlatformSettingRepository;
use Illuminate\Support\Facades\File;

function phpSourcesIn(string $directory): array
{
    if (! File::isDirectory($directory)) {
        return [];
    }

    return collect(File::allFiles($directory))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => File::get($file->getPathname())])
        ->all();
}

it('keeps controllers and form requests away from persistence details', function () {
    $sources = [
        ...phpSourcesIn(app_path('Http/Controllers')),
        ...phpSourcesIn(app_path('Http/Requests')),
    ];

    foreach ($sources as $source) {
        expect($source)
            ->not->toContain('use App\\Models\\')
            ->not->toContain('use App\\Repositories\\')
            ->not->toContain('use Illuminate\\Support\\Facades\\DB;')
            ->not->toContain('Illuminate\\Database\\Eloquent')
            ->not->toContain('Illuminate\\Database\\Query');
    }
});

it('keeps services away from Eloquent and query builder details', function () {
    foreach (phpSourcesIn(app_path('Services')) as $source) {
        expect($source)
            ->not->toContain('use App\\Models\\')
            ->not->toContain('Illuminate\\Database\\Eloquent')
            ->not->toContain('Illuminate\\Database\\Query');
    }
});

it('keeps repositories away from orchestration and external side effects', function () {
    foreach (phpSourcesIn(app_path('Repositories')) as $source) {
        expect($source)
            ->not->toContain('use App\\Services\\')
            ->not->toContain('use App\\Gateways\\')
            ->not->toContain('use App\\Events\\')
            ->not->toContain('use Illuminate\\Support\\Facades\\Http;')
            ->not->toContain('use Illuminate\\Support\\Facades\\Event;')
            ->not->toContain('use Illuminate\\Support\\Facades\\Notification;');
    }
});

it('resolves repository contracts to their infrastructure implementations', function () {
    expect(app(PlatformSettingRepositoryInterface::class))
        ->toBeInstanceOf(EloquentPlatformSettingRepository::class);
});
