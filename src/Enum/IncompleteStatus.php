<?php

namespace App\Enum;

enum IncompleteStatus: string
{
    case PENDING = 'PENDING';
    case SUBMITTED = 'SUBMITTED';
    case COMPLETED = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SUBMITTED => 'Submitted for Review',
            self::COMPLETED => 'Completed / Resolved',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning text-dark',
            self::SUBMITTED => 'bg-info text-dark',
            self::COMPLETED => 'bg-success text-white',
        };
    }
}
