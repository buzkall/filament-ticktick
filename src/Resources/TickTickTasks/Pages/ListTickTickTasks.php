<?php

namespace Buzkall\FilamentTicktick\Resources\TickTickTasks\Pages;

use Buzkall\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickTickTasks extends ListRecords
{
    protected static string $resource = TickTickTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
