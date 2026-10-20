<?php

namespace App\Sheets;

use App\Support\FilePath;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LocalSheetGateway implements SpreadsheetGateway
{
    /**
     * @var array<string, array<int, array<int, mixed>>>
     */
    protected array $tabs = [];

    protected ?string $path;

    public function __construct(?string $path = null)
    {
        $this->path = FilePath::absolute($path);

        if ($this->path && File::exists($this->path)) {
            $decoded = json_decode((string) File::get($this->path), true);

            if (is_array($decoded)) {
                $this->tabs = $decoded;
            }
        }
    }

    public function read(string $sheet): array
    {
        return $this->tabs[$sheet] ?? [];
    }

    public function append(string $sheet, array $values): void
    {
        if (empty($this->tabs[$sheet])) {
            throw new InvalidArgumentException("Sheet [{$sheet}] belum dibuat. Jalankan php artisan sheets:setup.");
        }

        $this->tabs[$sheet][] = array_values($values);

        $this->persist();
    }

    public function updateRow(string $sheet, int $rowNumber, array $values): void
    {
        $index = $rowNumber - 1;

        if (! isset($this->tabs[$sheet][$index])) {
            throw new InvalidArgumentException("Baris [{$rowNumber}] tidak ditemukan pada sheet [{$sheet}].");
        }

        $this->tabs[$sheet][$index] = array_values($values);

        $this->persist();
    }

    public function batchRead(array $sheets): array
    {
        $result = [];

        foreach ($sheets as $sheet) {
            $result[$sheet] = $this->read($sheet);
        }

        return $result;
    }

    public function ensureSheet(string $sheet, array $headers): bool
    {
        if (! empty($this->tabs[$sheet])) {
            return false;
        }

        $this->tabs[$sheet] = [array_values($headers)];

        $this->persist();

        return true;
    }

    public function healthCheck(): array
    {
        return [
            'driver' => 'local',
            'ok' => true,
            'path' => $this->path,
            'tabs' => array_map(fn (array $rows): int => max(0, count($rows) - 1), $this->tabs),
        ];
    }

    public function serviceAccountEmail(): ?string
    {
        return null;
    }

    public function spreadsheetUrl(): ?string
    {
        return null;
    }

    public function reset(): void
    {
        $this->tabs = [];

        $this->persist();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function seed(string $sheet, array $rows): void
    {
        $this->tabs[$sheet] = array_values($rows);

        $this->persist();
    }

    /**
     * @return array<int, string>
     */
    public function sheetTitles(): array
    {
        return array_keys($this->tabs);
    }

    protected function persist(): void
    {
        if (! $this->path) {
            return;
        }

        File::ensureDirectoryExists(dirname($this->path));

        File::put(
            $this->path,
            json_encode($this->tabs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
