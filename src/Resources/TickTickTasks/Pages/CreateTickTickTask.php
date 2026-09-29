<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Resources\Pages\CreateRecord;

/** @extends CreateRecord<TickTickTask> */
class CreateTickTickTask extends CreateRecord
{
    protected static string $resource = TickTickTaskResource::class;

    // the local change must be rolled back when TickTick rejects it
    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record) {
            return;
        }

        try {
            app(TickTickService::class)->push($record);
        } catch (TickTickException $exception) {
            TickTickTaskResource::notifyFailure($exception);

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }
}
