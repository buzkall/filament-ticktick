<?php

namespace Buzkall\FilamentTicktick;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentTicktickServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-ticktick')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_ticktick_tasks_table');
    }

    public function packageBooted(): void
    {
        //
    }
}
