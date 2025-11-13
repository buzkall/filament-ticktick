<?php

namespace Buzkall\FilamentTicktick;

use Buzkall\TickTick\TickTick;

class TickTickService
{
    protected TickTick $client;

    public function __construct()
    {
        $this->client = new TickTick([
            'access_token' => config('filament-ticktick.access_token'),
        ]);
    }

    public function getTasks(string $projectId): array
    {
        return $this->client->tasks()->all($projectId);
    }

    public function createTask(array $data)
    {
        return $this->client->tasks()->create($data);
    }

    public function updateTask(string $taskId, string $projectId, array $data)
    {
        return $this->client->tasks()->update($taskId, $projectId, $data);
    }

    public function deleteTask(string $taskId, string $projectId)
    {
        return $this->client->tasks()->delete($taskId, $projectId);
    }

    public function completeTask(string $taskId, string $projectId)
    {
        return $this->client->tasks()->complete($taskId, $projectId);
    }

    public function getProjects(): array
    {
        return $this->client->projects()->all();
    }
}