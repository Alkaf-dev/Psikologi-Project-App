<?php

namespace App\Filament\Support;

use Filament\Pages\Page;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class BaseSheetPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.sheet-page';

    /**
     * @var array<int, string>
     */
    protected array $searchableColumns = [];

    abstract public function table(Table $table): Table;

    public function showMascot(): bool
    {
        return false;
    }

    public function mascotContext(): string
    {
        return 'tasks';
    }

    protected function spreadsheetColumn(Column $column): Column
    {
        return $column
            ->extraHeaderAttributes([
                'style' => 'border-inline-end: 1px solid rgba(115, 115, 115, .30); border-block-end: 1px solid rgba(115, 115, 115, .30);',
            ])
            ->extraCellAttributes([
                'style' => 'border-inline-end: 1px solid rgba(115, 115, 115, .22); border-block-end: 1px solid rgba(115, 115, 115, .18);',
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     */
    protected function paginateRecords(
        Collection $records,
        ?string $search,
        ?string $sortColumn,
        ?string $sortDirection,
        int|string $page,
        int|string $recordsPerPage,
    ): LengthAwarePaginator {
        if (filled($search)) {
            $search = mb_strtolower(trim($search));
            $columns = $this->searchableColumns;

            $records = $records->filter(function (array $record) use ($search, $columns): bool {
                $values = $columns !== []
                    ? array_map(fn (string $column): string => (string) ($record[$column] ?? ''), $columns)
                    : array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $record);

                return str_contains(mb_strtolower(implode(' ', $values)), $search);
            });
        }

        if (filled($sortColumn) && in_array($sortDirection, ['asc', 'desc'], true)) {
            $records = $records
                ->sortBy(
                    fn (array $record): string => mb_strtolower((string) ($record[$sortColumn] ?? '')),
                    SORT_NATURAL,
                )
                ->when($sortDirection === 'desc', fn (Collection $sorted): Collection => $sorted->reverse())
                ->values();
        }

        $perPage = $recordsPerPage === 'all'
            ? max(1, $records->count())
            : max(1, (int) $recordsPerPage);

        $currentPage = max(1, (int) $page);

        return new LengthAwarePaginator(
            $records->forPage($currentPage, $perPage)->values(),
            $records->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
