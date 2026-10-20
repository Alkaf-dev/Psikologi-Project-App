<?php

namespace App\Sheets;

use App\Support\FilePath;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\AddSheetRequest;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\ValueRange;
use RuntimeException;
use Throwable;

class GoogleSheetsGateway implements SpreadsheetGateway
{
    protected ?Client $client = null;

    protected ?Sheets $service = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $titles = null;

    public function __construct(
        protected ?string $spreadsheetId,
        protected ?string $credentialsPath,
    ) {}

    public function read(string $sheet): array
    {
        $response = $this->service()->spreadsheets_values->get(
            $this->spreadsheetId(),
            $this->range($sheet, 'A:ZZ'),
            ['valueRenderOption' => 'UNFORMATTED_VALUE'],
        );

        return $response->getValues() ?? [];
    }

    public function append(string $sheet, array $values): void
    {
        $this->service()->spreadsheets_values->append(
            $this->spreadsheetId(),
            $this->range($sheet, 'A1'),
            new ValueRange(['values' => [array_values($values)]]),
            [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS',
            ],
        );
    }

    public function updateRow(string $sheet, int $rowNumber, array $values): void
    {
        $this->service()->spreadsheets_values->update(
            $this->spreadsheetId(),
            $this->range($sheet, 'A'.$rowNumber),
            new ValueRange(['values' => [array_values($values)]]),
            ['valueInputOption' => 'RAW'],
        );
    }

    public function batchRead(array $sheets): array
    {
        if (empty($sheets)) {
            return [];
        }

        $response = $this->service()->spreadsheets_values->batchGet(
            $this->spreadsheetId(),
            ['ranges' => array_map(fn (string $sheet): string => $this->range($sheet, 'A:ZZ'), $sheets)],
        );

        $result = [];

        foreach ($response->getValueRanges() as $index => $valueRange) {
            $sheet = $sheets[$index] ?? null;

            if ($sheet !== null) {
                $result[$sheet] = $valueRange->getValues() ?? [];
            }
        }

        return $result;
    }

    public function ensureSheet(string $sheet, array $headers): bool
    {
        $created = false;

        if (! in_array($sheet, $this->titles(), true)) {
            $this->service()->spreadsheets->batchUpdate(
                $this->spreadsheetId(),
                new BatchUpdateSpreadsheetRequest([
                    'requests' => [
                        new SheetsRequest([
                            'addSheet' => new AddSheetRequest([
                                'properties' => new SheetProperties(['title' => $sheet]),
                            ]),
                        ]),
                    ],
                ]),
            );

            $this->titles = null;
            $created = true;
        }

        $rows = $this->read($sheet);

        if ($created || empty($rows) || $this->isEmptyRow($rows[0])) {
            $this->service()->spreadsheets_values->update(
                $this->spreadsheetId(),
                $this->range($sheet, 'A1'),
                new ValueRange(['values' => [array_values($headers)]]),
                ['valueInputOption' => 'RAW'],
            );
        }

        return $created;
    }

    public function healthCheck(): array
    {
        try {
            $spreadsheet = $this->service()->spreadsheets->get(
                $this->spreadsheetId(),
                ['fields' => 'properties.title,sheets.properties.title'],
            );

            $titles = array_map(
                fn ($sheet): ?string => $sheet->getProperties()?->getTitle(),
                $spreadsheet->getSheets(),
            );

            $expected = array_keys((array) config('sheets.tabs', []));
            $missing = array_values(array_diff($expected, $titles));

            return [
                'driver' => 'google',
                'ok' => empty($missing),
                'title' => $spreadsheet->getProperties()?->getTitle(),
                'spreadsheet_url' => $this->spreadsheetUrl(),
                'service_account_email' => $this->serviceAccountEmail(),
                'tabs' => array_values(array_filter($titles)),
                'missing_tabs' => $missing,
            ];
        } catch (Throwable $exception) {
            return [
                'driver' => 'google',
                'ok' => false,
                'spreadsheet_url' => $this->spreadsheetUrl(),
                'service_account_email' => $this->serviceAccountEmail(),
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function serviceAccountEmail(): ?string
    {
        $path = FilePath::absolute($this->credentialsPath);

        if (! $path || ! is_file($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        return is_array($credentials) ? ($credentials['client_email'] ?? null) : null;
    }

    public function spreadsheetUrl(): ?string
    {
        if (blank($this->spreadsheetId)) {
            return null;
        }

        return 'https://docs.google.com/spreadsheets/d/'.$this->spreadsheetId.'/edit';
    }

    protected function spreadsheetId(): string
    {
        if (blank($this->spreadsheetId)) {
            throw new RuntimeException('GOOGLE_SHEETS_ID belum diisi di file .env.');
        }

        return $this->spreadsheetId;
    }

    protected function service(): Sheets
    {
        if ($this->service) {
            return $this->service;
        }

        $this->service = new Sheets($this->client());

        return $this->service;
    }

    protected function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $path = FilePath::absolute($this->credentialsPath);

        if (! $path || ! is_file($path)) {
            throw new RuntimeException(
                'File service account Google tidak ditemukan: '.($path ?: '(path kosong)').
                '. Letakkan JSON di storage/app/private/google/service-account.json atau isi GOOGLE_SERVICE_ACCOUNT_JSON.',
            );
        }

        $client = new Client;
        $client->setApplicationName(config('app.name', 'Psikologi Connect'));
        $client->setAuthConfig($path);
        $client->addScope(Sheets::SPREADSHEETS);

        return $this->client = $client;
    }

    /**
     * @return array<int, string>
     */
    protected function titles(): array
    {
        if ($this->titles !== null) {
            return $this->titles;
        }

        $spreadsheet = $this->service()->spreadsheets->get(
            $this->spreadsheetId(),
            ['fields' => 'sheets.properties.title'],
        );

        return $this->titles = array_values(array_filter(array_map(
            fn ($sheet): ?string => $sheet->getProperties()?->getTitle(),
            $spreadsheet->getSheets(),
        )));
    }

    protected function range(string $sheet, string $cells): string
    {
        $title = str_replace("'", "''", $sheet);

        return "'{$title}'!{$cells}";
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
