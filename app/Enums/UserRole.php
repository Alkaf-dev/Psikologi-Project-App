<?php

namespace App\Enums;

enum UserRole: string
{
    case Mahasiswa = 'mahasiswa';
    case SuperAdmin = 'super_admin';
    case Pjmk = 'pjmk';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::SuperAdmin => 'Super Admin',
            self::Pjmk => 'PJMK',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mahasiswa => 'info',
            self::SuperAdmin => 'danger',
            self::Pjmk => 'warning',
        };
    }

    public function isAdminPanel(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Pjmk], true);
    }

    public function isStudentPortal(): bool
    {
        return $this === self::Mahasiswa;
    }

    public static function tryFromLegacy(mixed $role): ?self
    {
        if ($role instanceof self) {
            return $role;
        }

        if (! is_string($role)) {
            return null;
        }

        $role = mb_strtolower(trim($role));

        if ($role === '') {
            return null;
        }

        return self::tryFrom($role) ?? ($role === 'admin' ? self::SuperAdmin : null);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function adminValues(): array
    {
        return array_values(array_map(
            fn (self $role): string => $role->value,
            array_filter(self::cases(), fn (self $role): bool => $role->isAdminPanel()),
        ));
    }
}
