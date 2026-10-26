<?php

namespace App\Repositories;

use App\Enums\UserRole;
use Illuminate\Support\Collection;

class UserRepository extends BaseSheetRepository
{
    protected string $table = 'users';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function students(): Collection
    {
        return $this->all()
            ->filter(fn (array $user): bool => $this->isStudent($user))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function admins(): Collection
    {
        return $this->all()
            ->filter(fn (array $user): bool => $this->isAdmin($user))
            ->values();
    }

    public function isAdmin(array $user): bool
    {
        return UserRole::tryFromLegacy($user['role'] ?? null)?->isStaff() ?? false;
    }

    public function isStudent(array $user): bool
    {
        return UserRole::tryFromLegacy($user['role'] ?? null)?->isStudent() ?? false;
    }

    public function hasOtherActiveAdmin(string $exceptId): bool
    {
        return $this->admins()->contains(
            fn (array $user): bool => (string) ($user['id'] ?? '') !== $exceptId && $this->isActive($user),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByLogin(string $login): ?array
    {
        $login = mb_strtolower(trim($login));

        if ($login === '') {
            return null;
        }

        return $this->all()->first(function (array $user) use ($login): bool {
            if (! $this->isActive($user)) {
                return false;
            }

            $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
            $username = $email !== '' ? explode('@', $email, 2)[0] : '';

            return $email === $login
                || $username === $login
                || mb_strtolower(trim((string) ($user['npm'] ?? ''))) === $login;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByLoginAndProdi(string $login, string $prodiId): ?array
    {
        $user = $this->findActiveByLogin($login);

        if (! $user) {
            return null;
        }

        return trim((string) ($user['prodi_id'] ?? '')) === trim($prodiId) ? $user : null;
    }

    public function countByProdi(string $prodiId, bool $withTrashed = false): int
    {
        return $this->all($withTrashed)
            ->filter(fn (array $user): bool => (string) ($user['prodi_id'] ?? '') === $prodiId)
            ->count();
    }

    public function emailExists(string $email, ?string $exceptId = null): bool
    {
        $email = mb_strtolower(trim($email));

        return $this->all()->contains(function (array $user) use ($email, $exceptId): bool {
            if ($exceptId !== null && (string) ($user['id'] ?? '') === $exceptId) {
                return false;
            }

            return mb_strtolower(trim((string) ($user['email'] ?? ''))) === $email;
        });
    }

    public function npmExists(string $npm, ?string $exceptId = null): bool
    {
        $npm = mb_strtolower(trim($npm));

        return $this->all()->contains(function (array $user) use ($npm, $exceptId): bool {
            if ($exceptId !== null && (string) ($user['id'] ?? '') === $exceptId) {
                return false;
            }

            return mb_strtolower(trim((string) ($user['npm'] ?? ''))) === $npm;
        });
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->students()
            ->mapWithKeys(function (array $user): array {
                $label = trim((string) ($user['name'] ?? 'Tanpa nama'));
                $npm = trim((string) ($user['npm'] ?? ''));

                return [(string) $user['id'] => $npm !== '' ? "{$label} ({$npm})" : $label];
            })
            ->all();
    }

    public function nameById(?string $id): string
    {
        $user = $this->find($id);

        return $user ? (string) ($user['name'] ?? '') : '';
    }

    /**
     * @return array<int, string>
     */
    public function classNames(): array
    {
        return $this->students()
            ->pluck('class_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function isActive(array $user): bool
    {
        return in_array(
            mb_strtolower(trim((string) ($user['active'] ?? ''))),
            ['1', 'true', 'yes', 'ya'],
            true,
        );
    }
}
