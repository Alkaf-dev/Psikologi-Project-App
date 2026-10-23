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
        return $panel->getId() === 'app' && $this->role() !== null;
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
        return $this->role()?->isStaff() ?? false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role() === UserRole::SuperAdmin;
    }

    public function isAdministrator(): bool
    {
        return $this->role() === UserRole::Admin;
    }

    public function isStudent(): bool
    {
        return $this->role()?->isStudent() ?? false;
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
