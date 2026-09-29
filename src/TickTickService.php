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

    /**
     * @return array<int, array<mixed>>
     */
    public function getProjects(): array
    {
        return $this->client->projects()->all();
    }

    /**
     * Projects keyed by id, grouped under their TickTick folder name. Projects
     * outside a folder, or in a folder that can't be resolved, stay at the top level.
     *
     * @return array<string, string|array<string, string>>
     */
    public function getProjectOptions(): array
    {
        return rescue(
            fn() => Cache::remember('filament-ticktick.projects', now()->addMinutes(5), fn() => $this->fetchProjectOptions()),
            // cached briefly, so a page listing many tasks doesn't retry a failing API once per row
            function(): array {
                Cache::put('filament-ticktick.projects', [], now()->addMinute());

                return [];
            },
            report: false,
        );
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    protected function fetchProjectOptions(): array
    {
        $groups = self::namesById(collect(rescue(fn() => $this->client->projectGroups()->all(), [], report: false))->sortBy('sortOrder'));

        $projects = collect($this->getProjects())->sortBy('sortOrder');

        $options = self::namesById($projects->reject(fn(array $project): bool => is_string($project['groupId'] ?? null) && isset($groups[$project['groupId']])));

        foreach ($groups as $groupId => $groupName) {
            $groupOptions = self::namesById($projects->where('groupId', $groupId));

            if ($groupOptions) {
                $options[$groupName] = $groupOptions;
            }
        }

        return $options;
    }

    /**
     * @param  iterable<array<mixed>>  $items
     * @return array<string, string>
     */
    protected static function namesById(iterable $items): array
    {
        $names = [];

        foreach ($items as $item) {
            if (is_string($item['id'] ?? null) && is_string($item['name'] ?? null)) {
                $names[$item['id']] = $item['name'];
            }
        }

        return $names;
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
        $previousProjectId = is_string($previousProjectId) ? $previousProjectId : null;
        $completed = $task->status === TaskStatus::Completed && $task->wasChanged('status');

        // a linked task always lives in a project, so a cleared project keeps the previous one
        if (! $task->project_id && $previousProjectId) {
            $task->forceFill(['project_id' => $previousProjectId])->saveQuietly();
        }

        [$ticktickId, $projectId] = $this->remoteIds($task);

        if ($previousProjectId && $projectId !== $previousProjectId) {
            $this->client->tasks()->moveTask($ticktickId, $previousProjectId, $projectId);

            // TickTick moves the sub-tasks along with their parent
            $task->subtasks()->update(['project_id' => $projectId]);
        }

        $this->client->tasks()->update($ticktickId, $projectId, $this->toPayload($task));

        if ($completed) {
            $this->complete($task);
        }
    }

    public function complete(TickTickTask $task): void
    {
        $this->client->tasks()->complete(...$this->remoteIds($task));
    }

    /**
     * TickTick has no reopen endpoint, a completed task is reopened by updating its status.
     */
    public function reopen(TickTickTask $task): void
    {
        [$ticktickId, $projectId] = $this->remoteIds($task);

        $this->client->tasks()->update($ticktickId, $projectId, ['status' => TaskStatus::Active->value]);
    }

    public function delete(TickTickTask $task): void
    {
        if (! $task->ticktick_id) {
            return;
        }

        $this->client->tasks()->delete(...$this->remoteIds($task));
    }

    /**
     * The TickTick task and project ids of a linked task.
     *
     * @return array{string, string}
     */
    protected function remoteIds(TickTickTask $task): array
    {
        if (! $task->ticktick_id || ! $task->project_id) {
            throw new TickTickException('The task is not linked to a TickTick task.');
        }

        return [$task->ticktick_id, $task->project_id];
    }

    /**
     * Project names keyed by id, without the folder grouping.
     *
     * @return array<string, string>
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
        if ($projectId === null) {
            return null;
        }

        if (str_starts_with($projectId, 'inbox')) {
            return __('filament-ticktick::resource.fields.inbox');
        }

        return $this->getProjectNames()[$projectId] ?? null;
    }

    /**
     * Imports the open tasks of the given projects into the local table. Use
     * `inbox` as the project id to import the inbox.
     *
     * @param  array<int, string>  $projectIds
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
     *
     * @return array<int, array<mixed>>
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<mixed>  $remote
     * @return array<string, mixed>
     */
    protected function fromPayload(array $remote): array
    {
        return [
            'title'      => $remote['title'] ?? '',
            'content'    => $remote['content'] ?? null,
            'project_id' => $remote['projectId'] ?? null,
            'start_date' => $this->parseDate($remote['startDate'] ?? null),
            'due_date'   => $this->parseDate($remote['dueDate'] ?? null),
            'priority'   => $remote['priority'] ?? 0,
            'status'     => $remote['status'] ?? 0,
            'tags'       => $remote['tags'] ?? [],
        ];
    }

    /**
     * TickTick dates come in UTC, they're stored in the app timezone.
     */
    protected function parseDate(mixed $date): ?Carbon
    {
        return is_string($date) ? Carbon::parse($date)->setTimezone(config()->string('app.timezone')) : null;
    }
}
