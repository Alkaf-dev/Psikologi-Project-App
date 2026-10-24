<?php

namespace App\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ApiTokenRepository extends BaseSheetRepository
{
    protected string $table = 'api_tokens';

    /**
     * @return array{plain_text: string, token: array<string, mixed>}
     */
    public function issue(string $userId, string $name, ?int $ttlDays = null): array
    {
        $id = (string) Str::ulid();
        $secret = Str::random(64);
        $plainText = "v1.{$id}.{$secret}";
        $ttlDays = max(1, min(365, $ttlDays ?? (int) config('api.token_ttl_days', 30)));

        $token = $this->create([
            'id' => $id,
            'user_id' => $userId,
            'name' => mb_substr(trim($name), 0, 100) ?: 'API Client',
            'token_hash' => hash('sha256', $plainText),
            'expires_at' => now()->addDays($ttlDays)->toIso8601String(),
        ]);

        return ['plain_text' => $plainText, 'token' => $token];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValid(string $plainText): ?array
    {
        $parts = explode('.', $plainText);

        if (count($parts) !== 3 || $parts[0] !== 'v1' || blank($parts[1]) || blank($parts[2])) {
            return null;
        }

        $token = $this->find($parts[1]);

        if ($token === null) {
            return null;
        }

        $expectedHash = (string) ($token['token_hash'] ?? '');

        if ($expectedHash === '' || ! hash_equals($expectedHash, hash('sha256', $plainText))) {
            return null;
        }

        if (trim((string) ($token['revoked_at'] ?? '')) !== '') {
            return null;
        }

        $expiresAt = trim((string) ($token['expires_at'] ?? ''));

        if ($expiresAt !== '') {
            try {
                if (CarbonImmutable::parse($expiresAt)->isPast()) {
                    return null;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return $token;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activeForUser(string $userId): Collection
    {
        return $this->all()
            ->filter(fn (array $token): bool => (string) ($token['user_id'] ?? '') === $userId)
            ->filter(fn (array $token): bool => $this->isActive($token))
            ->sortByDesc('created_at')
            ->values();
    }

    public function isActive(array $token): bool
    {
        if (trim((string) ($token['revoked_at'] ?? '')) !== '') {
            return false;
        }

        $expiresAt = trim((string) ($token['expires_at'] ?? ''));

        if ($expiresAt === '') {
            return true;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->isFuture();
        } catch (Throwable) {
            return false;
        }
    }

    public function revoke(string $id): bool
    {
        $token = $this->find($id);

        if ($token === null || trim((string) ($token['revoked_at'] ?? '')) !== '') {
            return false;
        }

        $this->update($id, ['revoked_at' => now()->toIso8601String()]);

        return true;
    }

    public function revokeAllForUser(string $userId, ?string $exceptId = null): int
    {
        $revoked = 0;

        foreach ($this->activeForUser($userId) as $token) {
            if ($exceptId !== null && (string) ($token['id'] ?? '') === $exceptId) {
                continue;
            }

            if ($this->revoke((string) $token['id'])) {
                $revoked++;
            }
        }

        return $revoked;
    }

    public function touch(array $token): void
    {
        $id = (string) ($token['id'] ?? '');

        if ($id === '') {
            return;
        }

        $lastUsedAt = trim((string) ($token['last_used_at'] ?? ''));

        if ($lastUsedAt !== '') {
            try {
                if (CarbonImmutable::parse($lastUsedAt)->isAfter(now()->subMinutes(15))) {
                    return;
                }
            } catch (Throwable) {
                // Perbarui timestamp yang tidak valid.
            }
        }

        $this->update($id, ['last_used_at' => now()->toIso8601String()]);
    }
}
