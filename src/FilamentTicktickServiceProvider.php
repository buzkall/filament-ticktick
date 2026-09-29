<?php

namespace Arzcode\FilamentTicktick;

use Arzcode\FilamentTicktick\Commands\UninstallCommand;
use Arzcode\FilamentTicktick\Support\PanelProviders;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Artisan;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\warning;

class FilamentTicktickServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-ticktick')
            ->hasTranslations()
            ->hasMigration('create_ticktick_tasks_table')
            ->hasCommand(UninstallCommand::class)
            ->hasInstallCommand(fn(InstallCommand $command) => $command
                ->startWith(fn(InstallCommand $cmd) => intro('Installing Filament TickTick'))
                ->publishMigrations()
                ->endWith(function(InstallCommand $cmd): void {
                    $steps = [
                        fn() => $this->runMigrations($cmd),
                        $this->configureAccessToken(...),
                        fn() => $this->publishFilamentAssets($cmd),
                        $this->patchPanelProviders(...),
                        fn() => outro('Filament TickTick install complete. Reload your Filament panel.'),
                    ];

                    foreach ($steps as $step) {
                        $cmd->newLine();
                        $step();
                    }
                }));
    }

    public function packageBooted(): void
    {
        FilamentAsset::register(
            [Css::make('filament-ticktick-styles', __DIR__ . '/../resources/css/filament-ticktick.css')],
            package: 'arzcode/filament-ticktick'
        );
    }

    protected function runMigrations(InstallCommand $command): void
    {
        if (! confirm(label: 'Would you like to run the migrations now?', default: true)) {
            note('Skipped — run `php artisan migrate` when you are ready.');

            return;
        }

        $command->call('migrate');
    }

    protected function configureAccessToken(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            warning('No .env file found — add TICKTICK_ACCESS_TOKEN to your environment manually.');

            return;
        }

        $contents = (string)file_get_contents($envPath);

        if (preg_match('/^TICKTICK_ACCESS_TOKEN=.+/m', $contents)) {
            note('TICKTICK_ACCESS_TOKEN already set in .env — leaving as-is.');

            return;
        }

        $token = password(
            label: 'Paste your TickTick access token',
            hint: 'Leave empty to add TICKTICK_ACCESS_TOKEN to .env later (see the README).',
        );

        if (blank($token)) {
            note('Skipped — add TICKTICK_ACCESS_TOKEN to .env before using the resource.');

            return;
        }

        $line = 'TICKTICK_ACCESS_TOKEN=' . $token;

        $patched = preg_match('/^TICKTICK_ACCESS_TOKEN=/m', $contents)
            ? preg_replace_callback('/^TICKTICK_ACCESS_TOKEN=.*$/m', fn() => $line, $contents)
            : rtrim($contents, "\n") . "\n\n" . $line . "\n";

        file_put_contents($envPath, $patched);
        info('Added TICKTICK_ACCESS_TOKEN to .env');
    }

    protected function publishFilamentAssets(InstallCommand $command): void
    {
        info('Publishing Filament assets…');
        Artisan::call('filament:assets', [], $command->getOutput());
    }

    protected function patchPanelProviders(): void
    {
        $files = PanelProviders::files();

        if ($files === []) {
            warning('No app/Providers/Filament/*PanelProvider.php found — register FilamentTicktickPlugin manually in your panel provider.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = str($file)->after(base_path() . DIRECTORY_SEPARATOR)->toString();

            if (PanelProviders::hasPlugin($contents)) {
                note(sprintf('FilamentTicktickPlugin already present in %s — leaving as-is.', $relative));

                continue;
            }

            $patched = PanelProviders::addPlugin($contents);

            if ($patched === null || ! PanelProviders::parses($patched)) {
                warning(sprintf('Could not patch %s — add ->plugins([FilamentTicktickPlugin::make()]) manually.', $relative));

                continue;
            }

            file_put_contents($file, $patched);
            info(sprintf('Patched %s to register FilamentTicktickPlugin.', $relative));
        }
    }
}
