<?php

namespace Buzkall\FilamentTicktick\Resources\TickTickTasks\Schemas;

use Buzkall\FilamentTicktick\Enums\TaskPriority;
use Buzkall\FilamentTicktick\Enums\TaskStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TickTickTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('filament-ticktick::resource.fields.title'))
                ->required()
                ->maxLength(255),

            Textarea::make('content')
                ->label(__('filament-ticktick::resource.fields.content'))
                ->rows(4),

            DateTimePicker::make('start_date')
                ->label(__('filament-ticktick::resource.fields.start_date')),

            DateTimePicker::make('due_date')
                ->label(__('filament-ticktick::resource.fields.due_date')),

            Select::make('priority')
                ->label(__('filament-ticktick::resource.fields.priority'))
                ->enum(TaskPriority::class)
                ->default(TaskPriority::None)
                ->required(),

            Select::make('status')
                ->label(__('filament-ticktick::resource.fields.status'))
                ->enum(TaskStatus::class)
                ->default(TaskStatus::Active)
                ->required(),

            TextInput::make('project_id')
                ->label(__('filament-ticktick::resource.fields.project_id')),

            TagsInput::make('tags')
                ->label(__('filament-ticktick::resource.fields.tags'))
                ->separator(','),

            TextInput::make('ticktick_id')
                ->label(__('filament-ticktick::resource.fields.ticktick_id'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}
