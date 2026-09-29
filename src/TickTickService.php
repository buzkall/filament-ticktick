<?php

namespace Arzcode\FilamentTicktick;

use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Arzcode\FilamentTicktick\Models\TickTickTask;
use Arzcode\TickTick\Exceptions\TickTickException;
use Arzcode\TickTick\TickTick;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class TickTickService
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:sO';

    public function __construct(protected TickTick $client) {}

    public function getProjects(): array
    {
        return $this->client->projects()->all();
    }

    /**
     * Projects keyed by id, grouped under their TickTick folder name. Projects
     * outside a folder, or in a folder that can't be resolved, stay at the top level.
     */
    public function getProjectOptions(): array
    {
        return rescue(
            fn() => Cache::remember('filament-ticktick.projects', now()->addMinutes(5), function(): array {
                $groups = collect(rescue(fn() => $this->client->projectGroups()->all(), [], report: false))
                    ->sortBy('sortOrder')
                    ->pluck('name', 'id');

                $projects = collect($this->getProjects())->sortBy('sortOrder');

                $options = $projects
                    ->reject(fn(array $project): bool => $groups->has($project['groupId'] ?? null))
                    ->pluck('name', 'id')
                    ->all();

                foreach ($groups as $groupId => $groupName) {
                    $groupOptions = $projects->where('groupId', $groupId)->pluck('name', 'id')->all();

                    if ($groupOptions) {
                        $options[$groupName] = $groupOptions;
                    }
                }

                return $options;
            }),
            // cached briefly, so a page listing many tasks doesn't retry a failing API once per row
            function(): array {
                Cache::put('filament-ticktick.projects', [], now()->addMinute());

                return [];
            },
            report: false,
        );
    }

    /**
     * Creates the task in TickTick, or updates it when it's already linked.
     */
    public function push(TickTickTask $task): void
    {
        if (! $task->ticktick_id) {
            $remote = $this->client->tasks()->create($this->toPayload($task));

            $task->forceFill([
                'ticktick_id' => $remote['id'],
                'project_id'  => $remote['projectId'],
            ])->saveQuietly();

            if ($task->status === TaskStatus::Completed) {
                $this->complete($task);
            }

            return;
        }

        $previousProjectId = $task->getPrevious()['project_id'] ?? null;
        $completed = $task->status === TaskStatus::Completed && $task->wasChanged('status');

        // a linked task always lives in a project, so a cleared project keeps the previous one
        if (! $task->project_id && $previousProjectId) {
            $task->forceFill(['project_id' => $previousProjectId])->saveQuietly();
        }

        if ($previousProjectId && $task->project_id !== $previousProjectId) {
            $this->client->tasks()->moveTask($task->ticktick_id, $previousProjectId, $task->project_id);

            // TickTick moves the sub-tasks along with their parent
            $task->subtasks()->update(['project_id' => $task->project_id]);
        }

        $this->client->tasks()->update($task->ticktick_id, $task->project_id, $this->toPayload($task));

        if ($completed) {
            $this->complete($task);
        }
    }

    public function complete(TickTickTask $task): void
    {
        $this->client->tasks()->complete($task->ticktick_id, $task->project_id);
    }

    /**
     * TickTick has no reopen endpoint, a completed task is reopened by updating its status.
     */
    public function reopen(TickTickTask $task): void
    {
        $this->client->tasks()->update($task->ticktick_id, $task->project_id, ['status' => TaskStatus::Active->value]);
    }

    public function delete(TickTickTask $task): void
    {
        if (! $task->ticktick_id) {
            return;
        }

        $this->client->tasks()->delete($task->ticktick_id, $task->project_id);
    }

    /**
     * Project names keyed by id, without the folder grouping.
     */
    public function getProjectNames(): array
    {
        $names = [];

        foreach ($this->getProjectOptions() as $key => $option) {
            is_array($option) ? $names += $option : $names[$key] = $option;
        }

        return $names;
    }

    public function getProjectName(?string $projectId): ?string
    {
        if (str_starts_with($projectId ?? '', 'inbox')) {
            return __('filament-ticktick::resource.fields.inbox');
        }

        return $this->getProjectNames()[$projectId] ?? null;
    }

    /**
     * Imports the open tasks of the given projects into the local table. Use
     * `inbox` as the project id to import the inbox.
     */
    public function pull(array $projectIds): int
    {
        $remoteTasks = collect($projectIds)->flatMap(fn(string $projectId) => $projectId === 'inbox'
            ? $this->inboxTasks()
            : $this->client->tasks()->all($projectId));

        $remoteTasks->each(fn(array $remote) => TickTickTask::updateOrCreate(
            ['ticktick_id' => $remote['id']],
            $this->fromPayload($remote),
        ));

        // sub-tasks can be listed before their parent, so they're linked once every task is saved
        $remoteTasks->each(fn(array $remote) => TickTickTask::where('ticktick_id', $remote['id'])->update([
            'parent_id' => isset($remote['parentId']) ? TickTickTask::where('ticktick_id', $remote['parentId'])->value('id') : null,
        ]));

        return $remoteTasks->count();
    }

    /**
     * Not every account can read the inbox through the `inbox` alias, TickTick
     * answers 404 then. Any other failure is reported like the other projects.
     */
    protected function inboxTasks(): array
    {
        try {
            return $this->client->tasks()->all('inbox');
        } catch (TickTickException $exception) {
            if ($exception->getStatusCode() === 404) {
                return [];
            }

            throw $exception;
        }
    }

    protected function toPayload(TickTickTask $task): array
    {
        // a linked task gets its dates even when empty, so clearing one in the form clears it in TickTick
        $clearable = $task->ticktick_id ? ['startDate', 'dueDate'] : [];

        return array_filter([
            'title'     => $task->title,
            'content'   => $task->content ?? '',
            'projectId' => $task->project_id,
            'parentId'  => $task->parent?->ticktick_id,
            'startDate' => $task->start_date?->format(self::DATE_FORMAT),
            'dueDate'   => $task->due_date?->format(self::DATE_FORMAT),
            'timeZone'  => config('app.timezone'),
            'priority'  => $task->priority->value,
            // completing goes through the dedicated endpoint
            'status' => $task->status === TaskStatus::Completed ? null : $task->status->value,
            'tags'   => $task->tags ?? [],
        ], fn($value, string $key) => ! is_null($value) || in_array($key, $clearable, true), ARRAY_FILTER_USE_BOTH);
    }

    protected function fromPayload(array $remote): array
    {
        return [
            'title'      => $remote['title'] ?? '',
            'content'    => $remote['content'] ?? null,
            'project_id' => $remote['projectId'] ?? null,
            'start_date' => isset($remote['startDate']) ? Carbon::parse($remote['startDate'])->setTimezone(config('app.timezone')) : null,
            'due_date'   => isset($remote['dueDate']) ? Carbon::parse($remote['dueDate'])->setTimezone(config('app.timezone')) : null,
            'priority'   => $remote['priority'] ?? 0,
            'status'     => $remote['status'] ?? 0,
            'tags'       => $remote['tags'] ?? [],
        ];
    }
}
