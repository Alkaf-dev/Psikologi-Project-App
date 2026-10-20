<?php

namespace App\Sheets;

interface SpreadsheetGateway
{
    /**
     * @return array<int, array<int, mixed>>
     */
    public function read(string $sheet): array;

    /**
     * @param  array<int, mixed>  $values
     */
    public function append(string $sheet, array $values): void;

    /**
     * @param  array<int, mixed>  $values
     */
    public function updateRow(string $sheet, int $rowNumber, array $values): void;

    /**
     * @param  array<int, string>  $sheets
     * @return array<string, array<int, array<int, mixed>>>
     */
    public function batchRead(array $sheets): array;

    /**
     * @param  array<int, string>  $headers
     */
    public function ensureSheet(string $sheet, array $headers): bool;

    /**
     * @return array<string, mixed>
     */
    public function healthCheck(): array;

    public function serviceAccountEmail(): ?string;

    public function spreadsheetUrl(): ?string;
}
