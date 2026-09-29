<?php

use Arzcode\FilamentTicktick\Support\PanelProviders;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * Both commands patch files of the testbench skeleton, so each test backs up
 * and restores what it touches.
 */
beforeEach(function() {
    $this->providersPath = app_path('Providers/Filament');
    $this->providerFile = $this->providersPath . '/TestPanelProvider.php';
    $this->envPath = base_path('.env');
    $this->originalEnv = file_exists($this->envPath) ? file_get_contents($this->envPath) : null;
    $this->migrationsPath = database_path('migrations');

    File::ensureDirectoryExists($this->providersPath);
    file_put_contents($this->providerFile, <<<'PHP'
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;
        use Filament\PanelProvider;

        class TestPanelProvider extends PanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->id('test')
                    ->path('test')
                    ->plugins([
                        SomeOtherPlugin::make(),
                    ]);
            }
        }
        PHP);

    file_put_contents($this->envPath, "APP_NAME=Testing\n");
});

afterEach(function() {
    File::deleteDirectory($this->providersPath);
    File::deleteDirectory(public_path('css/arzcode/filament-ticktick'));

    foreach (glob($this->migrationsPath . '/*_create_ticktick_tasks_table.php') ?: [] as $file) {
        unlink($file);
    }

    $this->originalEnv === null ? @unlink($this->envPath) : file_put_contents($this->envPath, $this->originalEnv);
});

it('installs the package: publishes the migration, stores the token and registers the plugin', function() {
    $this->artisan('filament-ticktick:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsQuestion('Paste your TickTick access token', 'secret-token')
        ->assertSuccessful();

    $provider = file_get_contents($this->providerFile);

    expect(glob($this->migrationsPath . '/*_create_ticktick_tasks_table.php'))->toHaveCount(1)
        ->and(file_get_contents($this->envPath))->toContain('TICKTICK_ACCESS_TOKEN=secret-token')
        ->and($provider)->toContain('use Arzcode\FilamentTicktick\FilamentTicktickPlugin;')
        ->and($provider)->toContain("SomeOtherPlugin::make(),\n                FilamentTicktickPlugin::make(),\n            ]);")
        ->and(public_path('css/arzcode/filament-ticktick/filament-ticktick-styles.css'))->toBeFile();
});

it('does not register the plugin twice or ask for a token already in .env', function() {
    file_put_contents($this->envPath, "TICKTICK_ACCESS_TOKEN=existing\n");

    foreach (range(1, 2) as $run) {
        $this->artisan('filament-ticktick:install')
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->assertSuccessful();
    }

    expect(substr_count(file_get_contents($this->providerFile), PanelProviders::ENTRY))->toBe(1)
        ->and(file_get_contents($this->envPath))->toBe("TICKTICK_ACCESS_TOKEN=existing\n");
});

it('adds a plugins call to a panel provider that has none', function() {
    $contents = <<<'PHP'
        <?php

        namespace App\Providers\Filament;

        use Filament\Panel;

        class AdminPanelProvider
        {
            public function panel(Panel $panel): Panel
            {
                return $panel
                    ->id('admin');
            }
        }
        PHP;

    expect(PanelProviders::addPlugin($contents))
        ->toContain("->id('admin')\n            ->plugins([\n                FilamentTicktickPlugin::make(),\n            ]);");
});

it('does not interpret a token as a regex replacement', function() {
    file_put_contents($this->envPath, "TICKTICK_ACCESS_TOKEN=\n");

    $this->artisan('filament-ticktick:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsQuestion('Paste your TickTick access token', 'a$1b\\0c')
        ->assertSuccessful();

    expect(file_get_contents($this->envPath))->toBe("TICKTICK_ACCESS_TOKEN=a$1b\\0c\n");
});

it('ignores brackets and quotes inside comments when adding the plugin', function() {
    $contents = <<<'PHP'
        <?php

        class AdminPanelProvider
        {
            public function panel($panel)
            {
                return $panel
                    /* don't close ]; here */
                    # nor ']' here
                    ->plugins([
                        A::make(), // ]
                    ]);
            }
        }
        PHP;

    $patched = PanelProviders::addPlugin($contents);

    expect(PanelProviders::parses($patched))->toBeTrue()
        ->and($patched)->toContain("A::make(), // ]\n                FilamentTicktickPlugin::make(),\n            ]);");
});

it('removes only the plugin entry, keeping the other plugins', function(string $before, string $after) {
    $wrap = fn(string $plugins) => "<?php\n\nuse Arzcode\\FilamentTicktick\\FilamentTicktickPlugin;\n\nclass P\n{\n    public function panel(\$panel)\n    {\n        return \$panel\n{$plugins};\n    }\n}\n";

    $removed = PanelProviders::removePlugin($wrap($before));

    expect($removed)->toBe(str_replace("use Arzcode\\FilamentTicktick\\FilamentTicktickPlugin;\n", '', $wrap($after)))
        ->and(PanelProviders::parses($removed))->toBeTrue();
})->with([
    'single line, last'  => ['            ->plugins([A::make(), FilamentTicktickPlugin::make()])', '            ->plugins([A::make()])'],
    'single line, first' => ['            ->plugins([FilamentTicktickPlugin::make(), A::make()])', '            ->plugins([A::make()])'],
    'own line, chained'  => [
        "            ->plugins([\n                A::make(),\n                FilamentTicktickPlugin::make()\n                    ->foo(['x']),\n                B::make(),\n            ])",
        "            ->plugins([\n                A::make(),\n                B::make(),\n            ])",
    ],
    'only plugin'         => ["            ->id('a')\n            ->plugins([\n                FilamentTicktickPlugin::make(),\n            ])", "            ->id('a')"],
    'plugin call'         => ["            ->id('a')\n            ->plugin(FilamentTicktickPlugin::make())\n            ->path('a')", "            ->id('a')\n            ->path('a')"],
    'plugin call, inline' => ["            ->id('a')->plugin(FilamentTicktickPlugin::make()->foo())->path('a')", "            ->id('a')->path('a')"],
]);

it('uninstalls the package, reversing the install', function() {
    Process::fake();

    $this->artisan('filament-ticktick:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsQuestion('Paste your TickTick access token', 'secret-token')
        ->assertSuccessful();

    $this->artisan('filament-ticktick:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'yes')
        ->expectsConfirmation('Would you like to drop the ticktick_tasks table?', 'yes')
        ->expectsConfirmation('Delete 1 published Filament TickTick migration file(s)?', 'yes')
        ->expectsConfirmation('Remove the TICKTICK_* entries from .env?', 'yes')
        ->assertSuccessful();

    expect(file_get_contents($this->providerFile))->not->toContain('FilamentTicktickPlugin')
        ->and(file_get_contents($this->providerFile))->toContain('SomeOtherPlugin::make(),')
        ->and(Schema::hasTable('ticktick_tasks'))->toBeFalse()
        ->and(glob($this->migrationsPath . '/*_create_ticktick_tasks_table.php'))->toBe([])
        ->and(public_path('css/arzcode/filament-ticktick'))->not->toBeDirectory()
        ->and(file_get_contents($this->envPath))->toBe("APP_NAME=Testing\n");

    Process::assertRan('composer remove arzcode/filament-ticktick');
});

it('changes nothing when the uninstall is not confirmed', function() {
    Process::fake();

    $this->artisan('filament-ticktick:uninstall')
        ->expectsConfirmation('Do you want to continue?', 'no')
        ->assertSuccessful();

    expect(Schema::hasTable('ticktick_tasks'))->toBeTrue();

    Process::assertNothingRan();
});
