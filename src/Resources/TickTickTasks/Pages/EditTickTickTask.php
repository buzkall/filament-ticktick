<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\CompleteTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\DeleteTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\ReopenTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Resources\Pages\EditRecord;

/** @extends EditRecord<TickTickTask> */
class EditTickTickTask extends EditRecord
{
    protected static string $resource = TickTickTaskResource::class;

    // the local change must be rolled back when TickTick rejects it
    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            CompleteTaskAction::make(),

            ReopenTaskAction::make(),

            DeleteTaskAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        try {
            app(TickTickService::class)->push($this->getRecord());
        } catch (TickTickException $exception) {
            TickTickTaskResource::notifyFailure($exception);

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }
}
