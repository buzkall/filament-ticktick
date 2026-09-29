<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions;

use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class PullTasksAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'pull';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-ticktick::resource.actions.pull'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('filament-ticktick::resource.actions.pull_submit'))
            ->schema([
                Select::make('projects')
                    ->label(__('filament-ticktick::resource.fields.projects'))
                    ->options(fn(): array => ['inbox' => __('filament-ticktick::resource.fields.inbox')]
                        + app(TickTickService::class)->getProjectOptions())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->extraAttributes(['class' => 'fi-ticktick-project-select']),
            ])
            ->action(function(array $data): void {
                try {
                    $count = app(TickTickService::class)->pull($data['projects']);
                } catch (TickTickException $exception) {
                    TickTickTaskResource::notifyFailure($exception);

                    return;
                }

                Notification::make()
                    ->title(__('filament-ticktick::resource.notifications.pulled', ['count' => $count]))
                    ->success()
                    ->send();
            });
    }
}
