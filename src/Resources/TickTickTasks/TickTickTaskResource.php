<?php

namespace Buzkall\FilamentTicktick\Resources\TickTickTasks;

use Buzkall\FilamentTicktick\Models\TickTickTask;
use Buzkall\FilamentTicktick\Resources\TickTickTasks\Pages\CreateTickTickTask;
use Buzkall\FilamentTicktick\Resources\TickTickTasks\Pages\EditTickTickTask;
use Buzkall\FilamentTicktick\Resources\TickTickTasks\Pages\ListTickTickTasks;
use Buzkall\FilamentTicktick\Resources\TickTickTasks\Schemas\TickTickTaskForm;
use Buzkall\FilamentTicktick\Resources\TickTickTasks\Tables\TickTickTasksTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TickTickTaskResource extends Resource
{
    protected static ?string $model = TickTickTask::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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

    public static function getPages(): array
    {
        return [
            'index'  => ListTickTickTasks::route('/'),
            'create' => CreateTickTickTask::route('/create'),
            'edit'   => EditTickTickTask::route('/{record}/edit'),
        ];
    }
}
