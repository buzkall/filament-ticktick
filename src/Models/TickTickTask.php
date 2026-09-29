<?php

namespace Arzcode\FilamentTicktick\Models;

use Arzcode\FilamentTicktick\Enums\TaskPriority;
use Arzcode\FilamentTicktick\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $content
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property TaskPriority $priority
 * @property TaskStatus $status
 * @property string|null $project_id
 * @property array<int, string>|null $tags
 * @property string|null $ticktick_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TickTickTask|null $parent
 */
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
        'parent_id',
    ];
    protected $attributes = [
        'status' => TaskStatus::Active,
    ];

    protected function casts(): array
    {
        return [
            'tags'       => 'array',
            'start_date' => 'datetime',
            'due_date'   => 'datetime',
            'priority'   => TaskPriority::class,
            'status'     => TaskStatus::class,
        ];
    }

    /** @return BelongsTo<TickTickTask, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<TickTickTask, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
