<?php

namespace Buzkall\FilamentTicktick\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskPriority: int implements HasLabel
{
    case None = 0;
    case Low = 1;
    case Medium = 3;
    case High = 5;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::None => __('filament-ticktick::enums.task_priority.none'),
            self::Low => __('filament-ticktick::enums.task_priority.low'),
            self::Medium => __('filament-ticktick::enums.task_priority.medium'),
            self::High => __('filament-ticktick::enums.task_priority.high'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'info',
            self::None => 'gray',
        };
    }
}
