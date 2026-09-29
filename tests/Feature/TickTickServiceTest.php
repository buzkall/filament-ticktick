<?php

use Arzcode\FilamentTicktick\Enums\TaskPriority;
use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\FilamentTicktick\TickTickService;
use Arzcode\TickTick\Exceptions\TickTickException;
use Illuminate\Support\Carbon;

function linkedTask(array $attributes = []): TickTickTask
{
    return TickTickTask::create(array_merge([
        'title'       => 'Linked',
        'priority'    => TaskPriority::None,
        'status'      => TaskStatus::Active,
        'project_id'  => 'p1',
        'ticktick_id' => 't1',
    ], $attributes));
}

test('push creates the task in TickTick and links it', function() {
    fakeTickTick(['POST /open/v1/task' => ['id' => 't1', 'projectId' => 'inbox123']], $history);

    $task = TickTickTask::create([
        'title'    => 'Write tests',
        'content'  => 'With testbench',
        'due_date' => Carbon::parse('2026-10-02 09:00', 'UTC'),
        'priority' => TaskPriority::High,
        'status'   => TaskStatus::Active,
        'tags'     => ['work', 'php'],
    ]);

    app(TickTickService::class)->push($task);

    expect(sentRequests($history))->toBe(['POST /open/v1/task'])
        ->and(sentPayload($history, 'POST /open/v1/task'))->toBe([
            'title'    => 'Write tests',
            'content'  => 'With testbench',
            'dueDate'  => '2026-10-02T09:00:00+0000',
            'timeZone' => 'UTC',
            'priority' => 5,
            'status'   => 0,
            'tags'     => ['work', 'php'],
        ])
        ->and($task->fresh())
        ->ticktick_id->toBe('t1')
        ->project_id->toBe('inbox123');
});

test('push completes a task created as completed', function() {
    fakeTickTick(['POST /open/v1/task' => ['id' => 't1', 'projectId' => 'p1']], $history);

    $task = TickTickTask::create(['title' => 'Done', 'priority' => TaskPriority::None, 'status' => TaskStatus::Completed]);

    app(TickTickService::class)->push($task);

    expect(sentRequests($history))->toBe(['POST /open/v1/task', 'POST /open/v1/project/p1/task/t1/complete'])
        ->and(sentPayload($history, 'POST /open/v1/task'))->not->toHaveKey('status');
});

test('push moves a linked task to its new project before updating it', function() {
    fakeTickTick(history: $history);

    $task = linkedTask();
    $task->update(['project_id' => 'p2', 'title' => 'Renamed']);

    app(TickTickService::class)->push($task);

    expect(sentRequests($history))->toBe(['POST /open/v1/task/move', 'POST /open/v1/task/t1'])
        ->and(sentPayload($history, 'POST /open/v1/task/move'))->toBe([['taskId' => 't1', 'fromProjectId' => 'p1', 'toProjectId' => 'p2']])
        ->and(sentPayload($history, 'POST /open/v1/task/t1'))->toMatchArray(['id' => 't1', 'projectId' => 'p2', 'title' => 'Renamed']);
});

test('push completes a linked task when its status changes to completed', function() {
    fakeTickTick(history: $history);

    $task = linkedTask();
    $task->update(['status' => TaskStatus::Completed]);

    app(TickTickService::class)->push($task);

    expect(sentRequests($history))->toBe(['POST /open/v1/task/t1', 'POST /open/v1/project/p1/task/t1/complete']);
});

test('push reopens a completed task through the update', function() {
    fakeTickTick(history: $history);

    $task = linkedTask(['status' => TaskStatus::Completed]);
    $task->update(['status' => TaskStatus::Active]);

    app(TickTickService::class)->push($task);

    expect(sentRequests($history))->toBe(['POST /open/v1/task/t1'])
        ->and(sentPayload($history, 'POST /open/v1/task/t1'))->toMatchArray(['status' => 0]);
});

test('push sends a cleared date so TickTick clears it too', function() {
    fakeTickTick(history: $history);

    $task = linkedTask(['due_date' => now()->addDay()]);
    $task->update(['due_date' => null]);

    app(TickTickService::class)->push($task);

    expect(sentPayload($history, 'POST /open/v1/task/t1'))->toHaveKey('dueDate', null)
        ->toHaveKey('startDate', null);
});

test('push keeps the previous project when it is cleared on a linked task', function() {
    fakeTickTick(history: $history);

    $task = linkedTask();
    $task->update(['project_id' => null, 'status' => TaskStatus::Completed]);

    app(TickTickService::class)->push($task);

    expect($task->fresh()->project_id)->toBe('p1')
        ->and(sentRequests($history))->toBe(['POST /open/v1/task/t1', 'POST /open/v1/project/p1/task/t1/complete'])
        ->and(sentPayload($history, 'POST /open/v1/task/t1'))->toMatchArray(['projectId' => 'p1']);
});

test('push throws when TickTick rejects the request', function() {
    fakeTickTick(['POST /open/v1/task' => jsonResponse(['errorMessage' => 'nope'], 500)]);

    $task = TickTickTask::create(['title' => 'Fails', 'priority' => TaskPriority::None, 'status' => TaskStatus::Active]);

    app(TickTickService::class)->push($task);
})->throws(TickTickException::class);

test('delete removes linked tasks from TickTick', function() {
    fakeTickTick(history: $history);

    app(TickTickService::class)->delete(linkedTask());

    expect(sentRequests($history))->toBe(['DELETE /open/v1/project/p1/task/t1']);
});

test('delete skips tasks that are not linked to TickTick', function() {
    fakeTickTick(history: $history);

    app(TickTickService::class)->delete(linkedTask(['ticktick_id' => null]));

    expect($history)->toBeEmpty();
});

test('pull imports the tasks of the selected projects and the inbox', function() {
    config(['app.timezone' => 'Europe/Madrid']);
    date_default_timezone_set('Europe/Madrid');

    fakeTickTick([
        'GET /open/v1/project/p1/data' => ['tasks' => [
            ['id' => 't1', 'projectId' => 'p1', 'title' => 'Updated remotely', 'priority' => 5, 'status' => -1, 'dueDate' => '2026-10-01T10:00:00.000+0000', 'tags' => ['a']],
        ]],
        'GET /open/v1/project/inbox/data' => ['tasks' => [
            ['id' => 't2', 'projectId' => 'inbox123', 'title' => 'From the inbox'],
        ]],
    ]);

    linkedTask(['title' => 'Old title']);

    expect(app(TickTickService::class)->pull(['p1', 'inbox']))->toBe(2)
        ->and(TickTickTask::count())->toBe(2)
        ->and(TickTickTask::firstWhere('ticktick_id', 't1'))
        ->title->toBe('Updated remotely')
        ->priority->toBe(TaskPriority::High)
        ->status->toBe(TaskStatus::Abandoned)
        ->tags->toBe(['a'])
        ->due_date->format('Y-m-d H:i')->toBe('2026-10-01 12:00')
        ->and(TickTickTask::firstWhere('ticktick_id', 't2'))
        ->project_id->toBe('inbox123')
        ->status->toBe(TaskStatus::Active);
});

test('pull links sub-tasks to their parent task', function() {
    fakeTickTick([
        'GET /open/v1/project/p1/data' => ['tasks' => [
            ['id' => 't2', 'projectId' => 'p1', 'title' => 'Sub-task', 'parentId' => 't1'],
            ['id' => 't1', 'projectId' => 'p1', 'title' => 'Parent'],
            ['id' => 't3', 'projectId' => 'p1', 'title' => 'No longer a sub-task'],
        ]],
    ]);

    $formerSubtask = linkedTask(['ticktick_id' => 't3', 'parent_id' => linkedTask(['ticktick_id' => 't4'])->id]);

    app(TickTickService::class)->pull(['p1']);

    $parent = TickTickTask::firstWhere('ticktick_id', 't1');

    expect($parent->parent_id)->toBeNull()
        ->and($parent->subtasks->pluck('ticktick_id')->all())->toBe(['t2'])
        ->and($formerSubtask->fresh()->parent_id)->toBeNull();
});

test('pull still imports project tasks when the inbox cannot be read', function() {
    fakeTickTick([
        'GET /open/v1/project/p1/data'    => ['tasks' => [['id' => 't1', 'projectId' => 'p1', 'title' => 'Work task']]],
        'GET /open/v1/project/inbox/data' => jsonResponse(['errorMessage' => 'not found'], 404),
    ]);

    expect(app(TickTickService::class)->pull(['p1', 'inbox']))->toBe(1);
});

test('pull reports an inbox failure other than a missing inbox', function() {
    fakeTickTick([
        'GET /open/v1/project/p1/data'    => ['tasks' => [['id' => 't1', 'projectId' => 'p1', 'title' => 'Work task']]],
        'GET /open/v1/project/inbox/data' => jsonResponse(['errorMessage' => 'unauthorized'], 401),
    ]);

    app(TickTickService::class)->pull(['p1', 'inbox']);
})->throws(TickTickException::class);

test('pull only requests the selected projects', function() {
    fakeTickTick([
        'GET /open/v1/project/p2/data' => ['tasks' => [['id' => 't2', 'projectId' => 'p2', 'title' => 'Personal task']]],
    ], $history);

    expect(app(TickTickService::class)->pull(['p2']))->toBe(1)
        ->and(sentRequests($history))->toBe(['GET /open/v1/project/p2/data']);
});

test('project options are cached', function() {
    fakeTickTick(['GET /open/v1/project' => [['id' => 'p1', 'name' => 'Work']]], $history);

    $service = app(TickTickService::class);

    expect($service->getProjectOptions())->toBe(['p1' => 'Work'])
        ->and($service->getProjectOptions())->toBe(['p1' => 'Work'])
        ->and($history)->toHaveCount(2);
});

test('project options are grouped by folder in TickTick order', function() {
    fakeTickTick([
        'GET /open/v1/project/group' => [
            ['id' => 'g2', 'name' => 'Personal', 'sortOrder' => 20],
            ['id' => 'g1', 'name' => 'Work', 'sortOrder' => 10],
            ['id' => 'g3', 'name' => 'Empty', 'sortOrder' => 30],
        ],
        'GET /open/v1/project' => [
            ['id' => 'p1', 'name' => 'Clients', 'groupId' => 'g1', 'sortOrder' => 2],
            ['id' => 'p2', 'name' => 'Health', 'groupId' => 'g2', 'sortOrder' => 1],
            ['id' => 'p3', 'name' => 'Internal', 'groupId' => 'g1', 'sortOrder' => 1],
            ['id' => 'p4', 'name' => 'Loose', 'sortOrder' => 1],
            ['id' => 'p5', 'name' => 'Orphan', 'groupId' => 'missing', 'sortOrder' => 2],
        ],
    ]);

    expect(app(TickTickService::class)->getProjectOptions())->toBe([
        'p4'       => 'Loose',
        'p5'       => 'Orphan',
        'Work'     => ['p3' => 'Internal', 'p1' => 'Clients'],
        'Personal' => ['p2' => 'Health'],
    ]);
});

test('project options stay flat when the folders cannot be read', function() {
    fakeTickTick([
        'GET /open/v1/project/group' => jsonResponse([], 500),
        'GET /open/v1/project'       => [['id' => 'p1', 'name' => 'Work', 'groupId' => 'g1']],
    ]);

    expect(app(TickTickService::class)->getProjectOptions())->toBe(['p1' => 'Work']);
});

test('project options are empty and only briefly cached when TickTick fails', function() {
    fakeTickTick(['GET /open/v1/project' => jsonResponse([], 401)], $history);

    $service = app(TickTickService::class);

    // one failed attempt (folders and projects) serves every row of a page
    expect($service->getProjectOptions())->toBe([])
        ->and($service->getProjectOptions())->toBe([])
        ->and($history)->toHaveCount(2);

    $this->travel(2)->minutes();

    $service->getProjectOptions();

    expect($history)->toHaveCount(4);
});
