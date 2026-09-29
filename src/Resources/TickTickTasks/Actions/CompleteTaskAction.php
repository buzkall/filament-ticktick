<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions;

use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class CompleteTaskAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'complete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-ticktick::resource.actions.complete'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn(TickTickTask $record): bool => $record->status !== TaskStatus::Completed && filled($record->ticktick_id))
            ->action(function(TickTickTask $record): void {
                try {
                    app(TickTickService::class)->complete($record);
                } catch (TickTickException $exception) {
                    TickTickTaskResource::notifyFailure($exception);

                    return;
                }

                $record->update(['status' => TaskStatus::Completed]);
            });
    }
}
