<?php

namespace Buzkall\FilamentTicktick\Models;

use Buzkall\FilamentTicktick\Enums\TaskPriority;
use Buzkall\FilamentTicktick\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;

class TickTickTask extends Model
{
    protected $table = 'ticktick_tasks';

    protected $fillable = [
        'title',
        'content',
        'start_date',
        'due_date',
        'priority',
        'status',
        'project_id',
        'tags',
        'ticktick_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
    ];
}
