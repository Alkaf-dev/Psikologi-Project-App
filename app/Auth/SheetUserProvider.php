<?php

namespace App\Auth;

use App\Repositories\UserRepository;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class SheetUserProvider implements UserProvider
{
    public function __construct(protected UserRepository $users) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        $user = $this->users->find((string) $identifier);

        if (! $user || ! $this->users->isActive($user)) {
            return null;
        }

        return $this->toSheetUser($user);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        //
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        $login = $credentials['login'] ?? $credentials['email'] ?? $credentials['npm'] ?? null;

        $prodiId = $credentials['prodi_id'] ?? null;

        if (! is_string($login) || trim($login) === '') {
            return null;
        }

        if (! is_string($prodiId) || trim($prodiId) === '') {
            return null;
        }

        $user = $this->users->findActiveByLoginAndProdi($login, $prodiId);

        return $user ? $this->toSheetUser($user) : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        $hash = (string) $user->getAuthPassword();

        return $hash !== '' && Hash::check($password, $hash);
    }

    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[\SensitiveParameter] array $credentials,
        bool $force = false,
    ): void {
        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return;
        }

        $hash = (string) $user->getAuthPassword();

        if (! $force && ! Hash::needsRehash($hash)) {
            return;
        }

        $this->users->update((string) $user->getAuthIdentifier(), [
            'password_hash' => Hash::make($password),
        ]);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function toSheetUser(array $user): GenericUser
    {
        return new SheetUser([
            'id' => (string) ($user['id'] ?? ''),
            'name' => (string) ($user['name'] ?? 'Pengguna'),
            'email' => (string) ($user['email'] ?? ''),
            'npm' => (string) ($user['npm'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'prodi_id' => (string) ($user['prodi_id'] ?? ''),
            'class_name' => (string) ($user['class_name'] ?? ''),
            'cohort' => (string) ($user['cohort'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'active' => $user['active'] ?? '0',
            'password' => (string) ($user['password_hash'] ?? ''),
            'remember_token' => null,
        ]);
    }
}
