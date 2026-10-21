<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationReadRepository extends BaseSheetRepository
{
    protected string $table = 'notification_reads';

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function forUser(string $userId): Collection
    {
        $userId = trim($userId);

        if ($userId === '') {
            return collect();
        }

        return $this->all()
            ->filter(fn (array $read): bool => (string) ($read['user_id'] ?? '') === $userId
                && trim((string) ($read['read_at'] ?? '')) !== '')
            ->keyBy('notification_key');
    }

    public function isRead(string $userId, string $key): bool
    {
        return $this->forUser($userId)->has($key);
    }

    public function markAsRead(string $userId, string $key): void
    {
        $this->markManyAsRead($userId, [$key]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function markManyAsRead(string $userId, array $keys): void
    {
        $userId = trim($userId);
        $keys = collect($keys)
            ->map(fn (mixed $key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->values();

        if ($userId === '' || $keys->isEmpty()) {
            return;
        }

        $now = now()->toIso8601String();
        $existing = $this->all(true)
            ->filter(fn (array $read): bool => (string) ($read['user_id'] ?? '') === $userId)
            ->keyBy('notification_key');

        $changed = false;

        foreach ($keys as $key) {
            $read = $existing->get($key);

            if ($read !== null) {
                if (
                    trim((string) ($read['read_at'] ?? '')) !== ''
                    && trim((string) ($read['deleted_at'] ?? '')) === ''
                ) {
                    continue;
                }

                $this->gateway->updateRow(
                    $this->table,
                    (int) $read['_row'],
                    $this->valuesForHeaders(array_merge($read, [
                        'read_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => '',
                    ])),
                );

                $changed = true;

                continue;
            }

            $this->gateway->append($this->table, $this->valuesForHeaders([
                'id' => (string) Str::ulid(),
                'user_id' => $userId,
                'notification_key' => $key,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => '',
            ]));

            $changed = true;
        }

        if ($changed) {
            $this->flushCache();
        }
    }
}
