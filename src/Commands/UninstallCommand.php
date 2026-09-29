<?php

namespace Arzcode\FilamentTicktick\Commands;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Support\PanelProviders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class UninstallCommand extends Command
{
    public $signature = 'filament-ticktick:uninstall';
    public $description = 'Reverse the Filament TickTick install: unregister the plugin, remove published assets, and optionally drop the table, migration, translations and .env entries.';

    public function handle(): int
    {
        intro('Uninstalling Filament TickTick');

        warning('This will remove Filament TickTick from your application.');

        if (! confirm(label: 'Do you want to continue?', default: false)) {
            note('Aborted.');

            return self::SUCCESS;
        }

        $steps = [
            $this->unpatchPanelProviders(...),
            $this->deletePublishedAssets(...),
            $this->dropTable(...),
            $this->deletePublishedMigrations(...),
            $this->deletePublishedTranslations(...),
            $this->removeEnvEntries(...),
            $this->runFinalSteps(...),
        ];

        foreach ($steps as $step) {
            $this->newLine();
            $step();
        }

        return self::SUCCESS;
    }

    protected function unpatchPanelProviders(): void
    {
        $files = PanelProviders::files();

        if ($files === []) {
            warning('No app/Providers/Filament/*PanelProvider.php found — nothing to clean.');

            return;
        }

        foreach ($files as $file) {
            $contents = (string)file_get_contents($file);
            $relative = $this->relativePath($file);

            if (! PanelProviders::hasPlugin($contents)) {
                note(sprintf('FilamentTicktickPlugin not present in %s — skipping.', $relative));

                continue;
            }

            $patched = PanelProviders::removePlugin($contents);

            if (! PanelProviders::parses($patched)) {
                warning(sprintf('Could not unpatch %s safely — remove FilamentTicktickPlugin from it manually.', $relative));

                continue;
            }

            if (PanelProviders::hasPlugin($patched)) {
                warning(sprintf('FilamentTicktickPlugin still referenced in %s — remove it manually.', $relative));
            }

            file_put_contents($file, $patched);
            info(sprintf('Removed FilamentTicktickPlugin from %s.', $relative));
        }
    }

    protected function deletePublishedAssets(): void
    {
        // Published by `php artisan filament:assets` into the host's public/
        // folder; removing the package won't clean it.
        $dir = public_path('css/arzcode/filament-ticktick');

        if (! is_dir($dir)) {
            note('No published Filament TickTick assets found — skipping.');

            return;
        }

        File::deleteDirectory($dir);
        info('Deleted ' . $this->relativePath($dir));
    }

    protected function dropTable(): void
    {
        $table = new TickTickTask()->getTable();

        if (! Schema::hasTable($table)) {
            note("No {$table} table found — skipping.");

            return;
        }

        if (! confirm(
            label: "Would you like to drop the {$table} table?",
            default: false,
            hint: 'This permanently deletes the local copy of your tasks (they stay in TickTick).',
        )) {
            note("Skipped — {$table} left in place.");

            return;
        }

        Schema::dropIfExists($table);
        info("Dropped {$table}.");
        warning('Its row remains in the `migrations` table; remove it manually if you also delete the migration file.');
    }

    protected function deletePublishedMigrations(): void
    {
        $files = glob(database_path('migrations/*_create_ticktick_tasks_table.php')) ?: [];

        if ($files === []) {
            note('No published Filament TickTick migration found — skipping.');

            return;
        }

        if (! confirm(label: sprintf('Delete %d published Filament TickTick migration file(s)?', count($files)), default: false)) {
            note('Skipped — published migrations left in place.');

            return;
        }

        foreach ($files as $file) {
            @unlink($file);
            note('Deleted ' . $this->relativePath($file));
        }

        info('Published migrations deleted.');
    }

    protected function deletePublishedTranslations(): void
    {
        $dir = lang_path('vendor/filament-ticktick');

        if (! is_dir($dir)) {
            note('No published translations found — skipping.');

            return;
        }

        if (! confirm(label: sprintf('Delete the published translations in %s?', $this->relativePath($dir)), default: false)) {
            note('Skipped — translations left in place.');

            return;
        }

        File::deleteDirectory($dir);
        info('Deleted ' . $this->relativePath($dir));
    }

    protected function removeEnvEntries(): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            note('No .env file found — skipping.');

            return;
        }

        $contents = (string)file_get_contents($envPath);

        if (! preg_match('/^TICKTICK_[A-Z_]+=/m', $contents)) {
            note('No TICKTICK_* entries in .env — skipping.');

            return;
        }

        if (! confirm(
            label: 'Remove the TICKTICK_* entries from .env?',
            default: false,
            hint: 'Keep them if another part of your application still talks to TickTick.',
        )) {
            note('Skipped — .env left untouched.');

            return;
        }

        $patched = preg_replace('/^TICKTICK_[A-Z_]+=.*\r?\n?/m', '', $contents) ?? $contents;
        file_put_contents($envPath, rtrim($patched, "\n") . "\n");
        info('Removed the TICKTICK_* entries from .env');
    }

    protected function runFinalSteps(): void
    {
        info('Removing the arzcode/filament-ticktick package…');

        $result = Process::path(base_path())
            ->forever()
            ->run('composer remove arzcode/filament-ticktick', function(string $type, string $output): void {
                $this->output->write($output);
            });

        if (! $result->successful()) {
            error('`composer remove arzcode/filament-ticktick` failed — run it manually to finish the uninstall.');

            return;
        }

        outro('Filament TickTick uninstall complete.');
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : $absolutePath;
    }
}
