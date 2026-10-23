<?php

namespace App\Filament\Concerns;

use App\Auth\SheetUser;

trait RequiresStudentRole
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof SheetUser && $user->isStudent();
    }
}
