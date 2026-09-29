<?php

namespace Arzcode\FilamentTicktick\Tests\Fixtures;

use Arzcode\FilamentTicktick\FilamentTicktickPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentTicktickPlugin::make(),
            ]);
    }
}
