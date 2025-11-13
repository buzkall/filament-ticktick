<?php

namespace Buzkall\FilamentTicktick\Resources\TickTickTasks\Pages;

use Buzkall\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTickTickTask extends EditRecord
{
    protected static string $resource = TickTickTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
