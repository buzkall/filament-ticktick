<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\Tables;

use Arzcode\FilamentTicktick\Enums\TaskPriority;
use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\CompleteTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\DeleteTasksBulkAction;
use Arzcode\FilamentTicktick\TickTickService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class TickTickTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // sub-tasks are listed inside their parent task
            ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('parent_id'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-ticktick::resource.fields.title'))
                    ->prefix(fn(TickTickTask $record): HtmlString => new HtmlString(sprintf(
                        '<span class="fi-ticktick-priority-dot" style="--dot-color: var(--%s-500)" title="%s"></span>',
                        $record->priority->getColor(),
                        e($record->priority->getLabel()),
                    )))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('subtasks_count')
                    ->label(__('filament-ticktick::resource.fields.subtasks'))
                    ->counts('subtasks')
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('project_id')
                    ->label(__('filament-ticktick::resource.fields.project_id'))
                    ->formatStateUsing(fn(?string $state): ?string => app(TickTickService::class)->getProjectName($state) ?? $state)
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('filament-ticktick::resource.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn(TaskStatus $state): string => $state->getLabel())
                    ->color(fn(TaskStatus $state): string => $state->getColor())
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('filament-ticktick::resource.fields.due_date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label(__('filament-ticktick::resource.fields.start_date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament-ticktick::resource.fields.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament-ticktick::resource.fields.updated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('priority')
                    ->label(__('filament-ticktick::resource.fields.priority'))
                    ->options(TaskPriority::class),

                SelectFilter::make('status')
                    ->label(__('filament-ticktick::resource.fields.status'))
                    ->options(TaskStatus::class),
            ])
            ->recordActions([
                CompleteTaskAction::make()->iconButton(),

                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteTasksBulkAction::make(),
                ]),
            ]);
    }
}
