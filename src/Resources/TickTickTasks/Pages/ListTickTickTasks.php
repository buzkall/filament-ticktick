<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages;

use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\PullTasksAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTickTickTasks extends ListRecords
{
    protected static string $resource = TickTickTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PullTasksAction::make(),

            CreateAction::make(),
        ];
    }
}
