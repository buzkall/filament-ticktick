<?php

namespace Buzkall\FilamentTicktick\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskStatus: int implements HasLabel
{
    case Active = 0;
    case Completed = 2;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => __('filament-ticktick::enums.task_status.active'),
            self::Completed => __('filament-ticktick::enums.task_status.completed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'warning',
            self::Completed => 'success',
        };
    }
}
