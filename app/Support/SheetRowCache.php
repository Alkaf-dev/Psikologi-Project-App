<?php

namespace App\Support;

use Illuminate\Support\Collection;

class SheetRowCache
{
    /**
     * @var array<string, Collection<int, array<string, mixed>>>
     */
    protected array $rows = [];

    /**
     * @param  callable(): Collection<int, array<string, mixed>>  $resolver
     * @return Collection<int, array<string, mixed>>
     */
    public function remember(string $table, bool $withTrashed, callable $resolver): Collection
    {
        $key = $table.':'.($withTrashed ? 'with-trashed' : 'active');

        if (! array_key_exists($key, $this->rows)) {
            $this->rows[$key] = $resolver();
        }

        return $this->rows[$key]->values();
    }

    public function flush(string $table): void
    {
        foreach (array_keys($this->rows) as $key) {
            if (str_starts_with($key, $table.':')) {
                unset($this->rows[$key]);
            }
        }
    }
}
