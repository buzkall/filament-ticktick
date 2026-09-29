<?php

namespace Arzcode\FilamentTicktick;

use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
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
        /** @var static $plugin */
        $plugin = app(static::class);

        return $plugin;
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::make()->getId());

        return $plugin;
    }
}
