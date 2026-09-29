<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions;

use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ReopenTaskAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'reopen';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-ticktick::resource.actions.reopen'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn(TickTickTask $record): bool => $record->status !== TaskStatus::Active && filled($record->ticktick_id))
            ->action(function(TickTickTask $record): void {
                try {
                    app(TickTickService::class)->reopen($record);
                } catch (TickTickException $exception) {
                    TickTickTaskResource::notifyFailure($exception);

                    return;
                }

                $record->update(['status' => TaskStatus::Active]);
            });
    }
}
