<?php

namespace App\Repositories;

class SettingsRepository extends BaseSheetRepository
{
    protected string $table = 'settings';

    /**
     * @return array<string, string>
     */
    public function allKeyed(): array
    {
        return $this->all()
            ->mapWithKeys(fn (array $setting): array => [
                (string) ($setting['key'] ?? '') => (string) ($setting['value'] ?? ''),
            ])
            ->all();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->all()->first(
            fn (array $setting): bool => (string) ($setting['key'] ?? '') === $key,
        );

        return $setting !== null ? (string) ($setting['value'] ?? '') : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $setting = $this->all()->first(
            fn (array $setting): bool => (string) ($setting['key'] ?? '') === $key,
        );

        if ($setting) {
            $this->gateway->updateRow(
                $this->table,
                (int) $setting['_row'],
                $this->valuesForHeaders(array_merge($setting, [
                    'value' => (string) $value,
                    'updated_at' => now()->toIso8601String(),
                ])),
            );

            $this->flushCache();

            return;
        }

        $this->create([
            'key' => $key,
            'value' => (string) $value,
        ]);
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value);
        }
    }
}
