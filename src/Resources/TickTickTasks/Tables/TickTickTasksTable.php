<?php

namespace Buzkall\FilamentTicktick\Resources\TickTickTasks\Tables;

use Buzkall\FilamentTicktick\Enums\TaskPriority;
use Buzkall\FilamentTicktick\Enums\TaskStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables;
use Filament\Tables\Table;

class TickTickTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-ticktick::resource.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('priority')
                    ->label(__('filament-ticktick::resource.fields.priority'))
                    ->badge()
                    ->formatStateUsing(fn (TaskPriority $state): string => $state->getLabel())
                    ->color(fn (TaskPriority $state): string => $state->getColor())
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament-ticktick::resource.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus $state): string => $state->getLabel())
                    ->color(fn (TaskStatus $state): string => $state->getColor())
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('filament-ticktick::resource.fields.due_date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label(__('filament-ticktick::resource.fields.start_date'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('filament-ticktick::resource.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('filament-ticktick::resource.fields.updated_at'))
                    ->dateTime()
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
                EditAction::make(),
            ])
            ->toolbarActions([
                /*BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),*/
            ]);
    }
}
