<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Schemas;

use Arzcode\FilamentTicktick\Enums\TaskPriority;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\TickTickService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TickTickTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema(static::mainComponents()),

                Section::make()
                    ->columnSpan(1)
                    ->schema(static::sidebarComponents()),
            ]);
    }

    /**
     * Sub-tasks always live in their parent's project, so there's no project to pick.
     */
    public static function configureSubtask(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ...static::mainComponents(),
                ...static::detailComponents(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function mainComponents(): array
    {
        return [
            TextInput::make('title')
                ->label(__('filament-ticktick::resource.fields.title'))
                ->required()
                ->maxLength(255),

            MarkdownEditor::make('content')
                ->label(__('filament-ticktick::resource.fields.content'))
                // uploads would live on this app's disk, which TickTick can't reach
                ->disableToolbarButtons(['attachFiles']),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function sidebarComponents(): array
    {
        return [
            Select::make('project_id')
                ->label(__('filament-ticktick::resource.fields.project_id'))
                ->placeholder(__('filament-ticktick::resource.fields.inbox'))
                ->options(function(?TickTickTask $record): array {
                    $options = app(TickTickService::class)->getProjectOptions();
                    $projectId = $record?->project_id;

                    return $projectId !== null && str_starts_with($projectId, 'inbox')
                        ? $options + [$projectId => __('filament-ticktick::resource.fields.inbox')]
                        : $options;
                })
                // an existing task can be moved to another project, but not out of every project
                ->selectablePlaceholder(fn(string $operation): bool => $operation === 'create')
                ->searchable()
                ->extraAttributes(['class' => 'fi-ticktick-project-select']),

            ...static::detailComponents(),

            TextEntry::make('ticktick_id')
                ->label(__('filament-ticktick::resource.fields.ticktick_id'))
                ->placeholder('-')
                ->visibleOn('edit'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function detailComponents(): array
    {
        return [
            DateTimePicker::make('start_date')
                ->label(__('filament-ticktick::resource.fields.start_date')),

            DateTimePicker::make('due_date')
                ->label(__('filament-ticktick::resource.fields.due_date')),

            ToggleButtons::make('priority')
                ->label(__('filament-ticktick::resource.fields.priority'))
                ->options(TaskPriority::class)
                ->grouped()
                ->inline()
                ->default(TaskPriority::None)
                ->required(),

            TagsInput::make('tags')
                ->label(__('filament-ticktick::resource.fields.tags')),
        ];
    }
}
