<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\DeleteAction;

class DeleteTaskAction extends DeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        // the task is deleted in TickTick first, and kept locally when that fails
        $this->before(function(DeleteAction $action, TickTickTask $record): void {
            try {
                app(TickTickService::class)->delete($record);
            } catch (TickTickException $exception) {
                TickTickTaskResource::notifyFailure($exception);

                $action->cancel();
            }
        });
    }
}
