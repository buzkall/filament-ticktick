<?php

namespace Buzkall\FilamentTicktick;

use Buzkall\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentTicktickPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-ticktick';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                TickTickTaskResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
