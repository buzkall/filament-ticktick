<?php

namespace Arzcode\FilamentTicktick\Resources\TickTickTasks\RelationManagers;

use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\CompleteTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Schemas\TickTickTaskForm;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\TickTickTaskResource;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubtasksRelationManager extends RelationManager
{
    protected static string $relationship = 'subtasks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-ticktick::resource.fields.subtasks');
    }

    public function form(Schema $schema): Schema
    {
        return TickTickTaskForm::configureSubtask($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modelLabel(__('filament-ticktick::resource.subtask_label'))
            ->pluralModelLabel(__('filament-ticktick::resource.fields.subtasks'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-ticktick::resource.fields.title'))
                    ->limit(50),

                TextColumn::make('status')
                    ->label(__('filament-ticktick::resource.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn(TaskStatus $state): string => $state->getLabel())
                    ->color(fn(TaskStatus $state): string => $state->getColor()),

                TextColumn::make('due_date')
                    ->label(__('filament-ticktick::resource.fields.due_date'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                CreateAction::make()
                    // sub-tasks live in their parent's project
                    ->mutateDataUsing(fn(array $data): array => [...$data, 'project_id' => $this->getOwnerRecord()->getAttribute('project_id')])
                    ->databaseTransaction()
                    ->after(fn(CreateAction $action, TickTickTask $record) => $this->push($action, $record)),
            ])
            ->recordActions([
                CompleteTaskAction::make()->iconButton(),

                EditAction::make()
                    ->iconButton()
                    ->databaseTransaction()
                    ->after(fn(EditAction $action, TickTickTask $record) => $this->push($action, $record)),

                DeleteAction::make()
                    ->iconButton()
                    ->before(function(DeleteAction $action, TickTickTask $record): void {
                        try {
                            app(TickTickService::class)->delete($record);
                        } catch (TickTickException $exception) {
                            TickTickTaskResource::notifyFailure($exception);

                            $action->cancel();
                        }
                    }),
            ]);
    }

    /**
     * The local change is rolled back when TickTick rejects it.
     */
    protected function push(Action $action, TickTickTask $record): void
    {
        try {
            app(TickTickService::class)->push($record);
        } catch (TickTickException $exception) {
            TickTickTaskResource::notifyFailure($exception);

            $action->halt(shouldRollBackDatabaseTransaction: true);
        }
    }
}
