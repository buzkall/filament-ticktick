<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\Collection;

class DeleteTasksBulkAction extends DeleteBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->using(fn(DeleteBulkAction $action, Collection $records) => $this->deleteRecords($action, $records));
    }

    /**
     * Each task is deleted in TickTick first, and kept locally when that fails.
     *
     * @param  Collection<int, TickTickTask>  $records
     */
    protected function deleteRecords(DeleteBulkAction $action, Collection $records): void
    {
        $records->each(function(TickTickTask $record) use ($action): void {
            try {
                app(TickTickService::class)->delete($record);
            } catch (TickTickException $exception) {
                $message = TickTickTaskResource::failureMessage($exception);
                $action->reportBulkProcessingFailure($message, $message);

                return;
            }

            $record->delete();
        });
    }
}
