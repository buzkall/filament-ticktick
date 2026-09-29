<?php

namespace Arzcode\FilamentTicktick\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: int implements HasColor, HasLabel
{
    case Abandoned = -1;
    case Active = 0;
    case Completed = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Abandoned => __('filament-ticktick::enums.task_status.abandoned'),
            self::Active    => __('filament-ticktick::enums.task_status.active'),
            self::Completed => __('filament-ticktick::enums.task_status.completed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Abandoned => 'gray',
            self::Active    => 'warning',
            self::Completed => 'success',
        };
    }
}
