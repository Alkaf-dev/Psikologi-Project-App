<?php

namespace App\Auth;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Support\Htmlable;

class SheetUser extends GenericUser implements FilamentUser, HasName
{
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'portal' => $this->isStudent(),
            default => false,
        };
    }

    public function getFilamentName(): string|Htmlable
    {
        return (string) ($this->attributes['name'] ?? 'Pengguna');
    }

    public function getKey(): mixed
    {
        return $this->getAuthIdentifier();
    }

    public function getAttributeValue(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function role(): ?UserRole
    {
        return UserRole::tryFromLegacy($this->attributes['role'] ?? null);
    }

    public function isAdmin(): bool
    {
        return $this->role()?->isAdminPanel() ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role() === UserRole::SuperAdmin;
    }

    public function isPjmk(): bool
    {
        return $this->role() === UserRole::Pjmk;
    }

    public function isStudent(): bool
    {
        return $this->role()?->isStudentPortal() ?? false;
    }

    public function isActive(): bool
    {
        return in_array(
            mb_strtolower(trim((string) ($this->attributes['active'] ?? ''))),
            ['1', 'true', 'yes', 'ya'],
            true,
        );
    }
}
