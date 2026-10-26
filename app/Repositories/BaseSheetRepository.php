<?php

namespace App\Repositories;

use App\Sheets\SpreadsheetGateway;
use App\Support\SheetRowCache;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

abstract class BaseSheetRepository
{
    protected string $table;

    public function __construct(protected SpreadsheetGateway $gateway) {}

    public function table(): string
    {
        return $this->table;
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return (array) config("sheets.tabs.{$this->table}", []);
    }

    public function flushCache(): void
    {
        app(SheetRowCache::class)->flush($this->table);

        Cache::forget($this->cacheKey());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(bool $withTrashed = false): Collection
    {
        return app(SheetRowCache::class)->remember(
            $this->table,
            $withTrashed,
            function () use ($withTrashed): Collection {
                $rows = $this->rawRows();

                if (empty($rows)) {
                    return collect();
                }

                $headers = array_values(array_filter(array_map(
                    fn (mixed $header): string => trim((string) $header),
                    $rows[0],
                )));

                if (empty($headers)) {
                    $headers = $this->headers();
                }

                $records = collect();

                foreach (array_slice($rows, 1) as $index => $row) {
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    $values = array_pad(
                        array_slice(array_values($row), 0, count($headers)),
                        count($headers),
                        '',
                    );

                    $record = array_combine($headers, $values) ?: [];
                    $record['_row'] = $index + 2;

                    if (! $withTrashed && trim((string) ($record['deleted_at'] ?? '')) !== '') {
                        continue;
                    }

                    $records->push($record);
                }

                return $records->values();
            },
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $id, bool $withTrashed = false): ?array
    {
        if (blank($id)) {
            return null;
        }

        return $this->all($withTrashed)->first(
            fn (array $record): bool => (string) ($record['id'] ?? '') === (string) $id,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBy(string $column, mixed $value, bool $withTrashed = false): ?array
    {
        return $this->all($withTrashed)->first(
            fn (array $record): bool => (string) ($record[$column] ?? '') === (string) $value,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $now = now()->toIso8601String();

        $data['id'] ??= (string) Str::ulid();
        $data['created_at'] ??= $now;
        $data['updated_at'] ??= $now;
        $data['deleted_at'] ??= '';

        $this->gateway->append($this->table, $this->valuesForHeaders($data));

        $this->flushCache();

        return $this->find((string) $data['id'], true) ?? $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $id, array $data): array
    {
        $record = $this->find($id, true);

        if (! $record) {
            throw new RuntimeException("Data dengan ID [{$id}] tidak ditemukan pada sheet [{$this->table}].");
        }

        unset($data['id'], $data['created_at'], $data['_row']);

        $data['updated_at'] = now()->toIso8601String();

        $merged = array_merge($record, $data);

        $this->gateway->updateRow(
            $this->table,
            (int) $record['_row'],
            $this->valuesForHeaders($merged),
        );

        $this->flushCache();

        return array_merge($merged, ['_row' => $record['_row']]);
    }

    public function delete(string $id): bool
    {
        $record = $this->find($id);

        if (! $record) {
            return false;
        }

        $this->update($id, ['deleted_at' => now()->toIso8601String()]);

        return true;
    }

    public function restore(string $id): bool
    {
        $record = $this->find($id, true);

        if (! $record) {
            return false;
        }

        $this->update($id, ['deleted_at' => '']);

        return true;
    }

    public function count(bool $withTrashed = false): int
    {
        return $this->all($withTrashed)->count();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function rawRows(): array
    {
        $ttl = max(1, (int) config('sheets.cache_ttl', 60));

        return Cache::remember(
            $this->cacheKey(),
            now()->addSeconds($ttl),
            fn (): array => $this->gateway->read($this->table),
        );
    }

    protected function cacheKey(): string
    {
        return "sheet:{$this->table}";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>
     */
    protected function valuesForHeaders(array $data): array
    {
        return array_map(
            fn (string $header): mixed => $this->normalizeValue($data[$header] ?? ''),
            $this->headers(),
        );
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $value;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
