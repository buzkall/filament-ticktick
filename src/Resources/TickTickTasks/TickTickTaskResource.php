<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks;

use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\CreateTickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\EditTickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\ListTickTickTasks;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\RelationManagers\SubtasksRelationManager;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Schemas\TickTickTaskForm;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Tables\TickTickTasksTable;
use Arzcode\TickTick\Exceptions\TickTickException;
use Arzcode\TickTick\TickTick;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use GuzzleHttp\Exception\ConnectException;

class TickTickTaskResource extends Resource
{
    protected static ?string $model = TickTickTask::class;
    protected static ?string $slug = 'ticktick-tasks';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getModelLabel(): string
    {
        return __('filament-ticktick::resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-ticktick::resource.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-ticktick::resource.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return TickTickTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TickTickTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubtasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTickTickTasks::route('/'),
            'create' => CreateTickTickTask::route('/create'),
            'edit'   => EditTickTickTask::route('/{record}/edit'),
        ];
    }

    public static function notifyFailure(TickTickException $exception): void
    {
        Notification::make()
            ->title(__('filament-ticktick::resource.notifications.failed'))
            ->body(static::failureMessage($exception))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * The exception messages come from arzcode/laravel-ticktick in English, so
     * known failures are mapped to translated messages and the rest are reported.
     */
    public static function failureMessage(TickTickException $exception): string
    {
        $status = $exception->getStatusCode();

        $reason = match (true) {
            blank(app(TickTick::class)->getAccessToken())         => 'missing_token',
            $exception->getPrevious() instanceof ConnectException => 'connection',
            in_array($status, [401, 403], true)                   => 'unauthorized',
            $status === 404                                       => 'not_found',
            $status === 429                                       => 'rate_limited',
            $status >= 500                                        => 'server_error',
            default                                               => 'unexpected',
        };

        if ($reason === 'unexpected') {
            report($exception);
        }

        return __("filament-ticktick::resource.notifications.errors.{$reason}");
    }
}
