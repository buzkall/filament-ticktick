<?php

use Arzcode\FilamentTicktick\Enums\TaskPriority;
use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\DeleteTaskAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Actions\DeleteTasksBulkAction;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\CreateTickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\EditTickTickTask;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\Pages\ListTickTickTasks;
use Arzcode\FilamentTicktick\Resources\TickTickTasks\RelationManagers\SubtasksRelationManager;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

function storedTask(array $attributes = []): TickTickTask
{
    return TickTickTask::create(array_merge([
        'title'       => 'Stored',
        'priority'    => TaskPriority::None,
        'status'      => TaskStatus::Active,
        'project_id'  => 'p1',
        'ticktick_id' => 't1',
    ], $attributes));
}

test('the list page renders the tasks', function() {
    fakeTickTick();

    $tasks = collect([storedTask(), storedTask(['ticktick_id' => 't2', 'title' => 'Another'])]);

    livewire(ListTickTickTasks::class)
        ->assertOk()
        ->assertCanSeeTableRecords($tasks);
});

test('sub-tasks are listed inside their parent task instead of the list page', function() {
    fakeTickTick();

    $parent = storedTask();
    $subtask = storedTask(['ticktick_id' => 't2', 'title' => 'Sub-task', 'parent_id' => $parent->id]);

    livewire(ListTickTickTasks::class)
        ->assertCanSeeTableRecords([$parent])
        ->assertCanNotSeeTableRecords([$subtask]);

    subtasksOf($parent)
        ->assertOk()
        ->assertCanSeeTableRecords([$subtask]);
});

function subtasksOf(TickTickTask $parent)
{
    return livewire(SubtasksRelationManager::class, ['ownerRecord' => $parent, 'pageClass' => EditTickTickTask::class]);
}

test('creating a sub-task sends it to TickTick under its parent', function() {
    fakeTickTick(['POST /open/v1/task' => ['id' => 't2', 'projectId' => 'p1']], $history);

    $parent = storedTask();

    subtasksOf($parent)
        ->callAction(TestAction::make('create')->table(), ['title' => 'Sub-task'])
        ->assertHasNoFormErrors();

    expect(sentRequests($history))->toBe(['POST /open/v1/task'])
        ->and(sentPayload($history, 'POST /open/v1/task'))->toMatchArray(['title' => 'Sub-task', 'projectId' => 'p1', 'parentId' => 't1'])
        ->and($parent->subtasks()->sole())
        ->ticktick_id->toBe('t2')
        ->project_id->toBe('p1');
});

test('the sub-task is not created when TickTick fails', function() {
    fakeTickTick(['POST /open/v1/task' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    $parent = storedTask();

    subtasksOf($parent)
        ->callAction(TestAction::make('create')->table(), ['title' => 'Never stored'])
        ->assertNotified(__('filament-ticktick::resource.notifications.failed'));

    expect($parent->subtasks()->count())->toBe(0);
});

test('editing a sub-task updates it in TickTick', function() {
    fakeTickTick(history: $history);

    $parent = storedTask();
    $subtask = storedTask(['ticktick_id' => 't2', 'title' => 'Sub-task', 'parent_id' => $parent->id]);

    subtasksOf($parent)
        ->callAction(TestAction::make('edit')->table($subtask), ['title' => 'Renamed'])
        ->assertHasNoFormErrors();

    expect(sentRequests($history))->toBe(['POST /open/v1/task/t2'])
        ->and(sentPayload($history, 'POST /open/v1/task/t2'))->toMatchArray(['title' => 'Renamed', 'parentId' => 't1'])
        ->and($subtask->fresh()->title)->toBe('Renamed');
});

test('the sub-task changes are rolled back when TickTick fails', function() {
    fakeTickTick(['POST /open/v1/task/t2' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    $parent = storedTask();
    $subtask = storedTask(['ticktick_id' => 't2', 'title' => 'Sub-task', 'parent_id' => $parent->id]);

    subtasksOf($parent)
        ->callAction(TestAction::make('edit')->table($subtask), ['title' => 'Renamed'])
        ->assertNotified(__('filament-ticktick::resource.notifications.failed'));

    expect($subtask->fresh()->title)->toBe('Sub-task');
});

test('deleting a sub-task removes it from TickTick', function() {
    fakeTickTick(history: $history);

    $parent = storedTask();
    $subtask = storedTask(['ticktick_id' => 't2', 'parent_id' => $parent->id]);

    subtasksOf($parent)->callAction(TestAction::make('delete')->table($subtask));

    expect(sentRequests($history))->toBe(['DELETE /open/v1/project/p1/task/t2'])
        ->and($subtask->exists())->toBeTrue()
        ->and(TickTickTask::find($subtask->id))->toBeNull();
});

test('moving a task to another project moves its sub-tasks too', function() {
    fakeTickTick(['GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work'], ['id' => 'p2', 'name' => 'Personal']]]);

    $parent = storedTask();
    $subtask = storedTask(['ticktick_id' => 't2', 'parent_id' => $parent->id]);

    livewire(EditTickTickTask::class, ['record' => $parent->getRouteKey()])
        ->fillForm(['project_id' => 'p2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($subtask->fresh()->project_id)->toBe('p2');
});

test('the list page shows the sub-task count', function() {
    fakeTickTick();

    $parent = storedTask();
    storedTask(['ticktick_id' => 't2', 'parent_id' => $parent->id]);
    storedTask(['ticktick_id' => 't3', 'parent_id' => $parent->id]);

    livewire(ListTickTickTasks::class)
        ->assertTableColumnStateSet('subtasks_count', 2, $parent);
});

test('creating a task sends it to TickTick', function() {
    fakeTickTick([
        'GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work']],
        'POST /open/v1/task'   => ['id' => 't1', 'projectId' => 'p1'],
    ], $history);

    livewire(CreateTickTickTask::class)
        ->fillForm([
            'title'      => 'From Filament',
            'project_id' => 'p1',
            'priority'   => TaskPriority::Medium,
            'tags'       => ['work'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(sentRequests($history))->toBe(['POST /open/v1/task'])
        ->and(sentPayload($history, 'POST /open/v1/task'))->toMatchArray(['title' => 'From Filament', 'projectId' => 'p1', 'priority' => 3, 'tags' => ['work']])
        ->and(TickTickTask::sole())
        ->ticktick_id->toBe('t1')
        ->tags->toBe(['work']);
});

test('the task is not created when TickTick fails', function() {
    fakeTickTick(['POST /open/v1/task' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    livewire(CreateTickTickTask::class)
        ->fillForm(['title' => 'Never stored'])
        ->call('create')
        ->assertNotified(__('filament-ticktick::resource.notifications.failed'));

    expect(TickTickTask::count())->toBe(0);
});

test('saving a task updates it in TickTick', function() {
    fakeTickTick(['GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work']]], $history);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->fillForm(['title' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(sentRequests($history))->toBe(['POST /open/v1/task/t1'])
        ->and($task->fresh()->title)->toBe('Renamed');
});

test('a project inside a folder can be selected', function() {
    fakeTickTick([
        'GET /open/v1/project/group' => [['id' => 'g1', 'name' => 'Clients', 'sortOrder' => 1]],
        'GET /open/v1/project'       => [
            ['id' => 'p1', 'name' => 'Work'],
            ['id' => 'p2', 'name' => 'Acme', 'groupId' => 'g1'],
        ],
    ], $history);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->fillForm(['project_id' => 'p2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(sentRequests($history))->toBe(['POST /open/v1/task/move', 'POST /open/v1/task/t1'])
        ->and($task->fresh()->project_id)->toBe('p2');
});

test('the local changes are rolled back when TickTick fails', function() {
    fakeTickTick([
        'GET /open/v1/project'  => [['id' => 'p1', 'name' => 'Work']],
        'POST /open/v1/task/t1' => jsonResponse(['errorMessage' => 'nope'], 500),
    ]);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->fillForm(['title' => 'Renamed'])
        ->call('save')
        ->assertNotified(__('filament-ticktick::resource.notifications.failed'));

    expect($task->fresh()->title)->toBe('Stored');
});

test('deleting a task removes it from TickTick', function() {
    fakeTickTick(history: $history);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->callAction(DeleteTaskAction::class);

    expect(sentRequests($history))->toBe(['DELETE /open/v1/project/p1/task/t1'])
        ->and(TickTickTask::count())->toBe(0);
});

test('the task is kept when TickTick cannot delete it', function() {
    fakeTickTick(['DELETE /open/v1/project/p1/task/t1' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->callAction(DeleteTaskAction::class)
        ->assertNotified(__('filament-ticktick::resource.notifications.failed'));

    expect(TickTickTask::count())->toBe(1);
});

test('the bulk delete removes the tasks from TickTick', function() {
    fakeTickTick(history: $history);

    $first = storedTask();
    $second = storedTask(['ticktick_id' => 't2']);

    livewire(ListTickTickTasks::class)
        ->selectTableRecords([$first, $second])
        ->callAction(TestAction::make(DeleteTasksBulkAction::class)->table()->bulk());

    expect(sentRequests($history))->toEqualCanonicalizing(['DELETE /open/v1/project/p1/task/t1', 'DELETE /open/v1/project/p1/task/t2'])
        ->and(TickTickTask::count())->toBe(0);
});

test('the bulk delete keeps the tasks TickTick cannot delete', function() {
    fakeTickTick(['DELETE /open/v1/project/p1/task/t1' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    $failing = storedTask();
    $deleted = storedTask(['ticktick_id' => 't2']);

    livewire(ListTickTickTasks::class)
        ->selectTableRecords([$failing, $deleted])
        ->callAction(TestAction::make(DeleteTasksBulkAction::class)->table()->bulk());

    expect(TickTickTask::pluck('id')->all())->toBe([$failing->id]);
});

test('the TickTick id is only shown when editing', function() {
    fakeTickTick();

    livewire(CreateTickTickTask::class)
        ->assertSchemaComponentHidden('ticktick_id');

    livewire(EditTickTickTask::class, ['record' => storedTask()->getRouteKey()])
        ->assertSchemaComponentVisible('ticktick_id')
        ->assertSee('t1');
});

test('the import action pulls the tasks from TickTick', function() {
    fakeTickTick([
        'GET /open/v1/project'         => [['id' => 'p1', 'name' => 'Work']],
        'GET /open/v1/project/p1/data' => ['tasks' => [['id' => 't1', 'projectId' => 'p1', 'title' => 'Remote']]],
    ]);

    livewire(ListTickTickTasks::class)
        ->callAction('pull', ['projects' => ['p1']])
        ->assertHasNoActionErrors()
        ->assertNotified(__('filament-ticktick::resource.notifications.pulled', ['count' => 1]));

    expect(TickTickTask::sole()->title)->toBe('Remote');
});

test('the import action requires at least one project', function() {
    fakeTickTick(['GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work']]], $history);

    livewire(ListTickTickTasks::class)
        ->callAction('pull', ['projects' => []])
        ->assertHasActionErrors(['projects' => 'required']);

    expect(sentRequests($history))->toBeEmpty();
});

test('the table shows the project name', function() {
    fakeTickTick(['GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work']]]);

    $task = storedTask(['project_id' => 'p1']);

    livewire(ListTickTickTasks::class)
        ->assertTableColumnFormattedStateSet('project_id', 'Work', $task);
});

test('the complete action completes the task in TickTick', function() {
    fakeTickTick(history: $history);

    $task = storedTask();

    livewire(ListTickTickTasks::class)
        ->callAction(TestAction::make('complete')->table($task));

    expect(sentRequests($history))->toBe(['POST /open/v1/project/p1/task/t1/complete'])
        ->and($task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('the task can be completed and reopened from the edit page', function() {
    fakeTickTick(history: $history);

    $task = storedTask();

    livewire(EditTickTickTask::class, ['record' => $task->getRouteKey()])
        ->assertActionHidden('reopen')
        ->callAction('complete')
        ->assertActionHidden('complete')
        ->callAction('reopen')
        ->assertActionVisible('complete');

    expect(sentRequests($history))->toBe(['POST /open/v1/project/p1/task/t1/complete', 'POST /open/v1/task/t1'])
        ->and(sentPayload($history, 'POST /open/v1/task/t1'))->toMatchArray(['status' => TaskStatus::Active->value])
        ->and($task->fresh()->status)->toBe(TaskStatus::Active);
});

test('new tasks are created as active', function() {
    fakeTickTick(['POST /open/v1/task' => ['id' => 't1', 'projectId' => 'inbox123']]);

    livewire(CreateTickTickTask::class)
        ->fillForm(['title' => 'New'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TickTickTask::sole()->status)->toBe(TaskStatus::Active);
});

test('the complete action is hidden for completed and unlinked tasks', function() {
    fakeTickTick();

    $completed = storedTask(['status' => TaskStatus::Completed]);
    $unlinked = storedTask(['ticktick_id' => null]);

    livewire(ListTickTickTasks::class)
        ->assertActionHidden(TestAction::make('complete')->table($completed))
        ->assertActionHidden(TestAction::make('complete')->table($unlinked));
});
