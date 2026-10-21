<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PengumumanPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $title = 'Pengumuman';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Informasi';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['title', 'body', 'author_name'];

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                ?string $search,
                ?string $sortColumn,
                ?string $sortDirection,
                int|string $page,
                int|string $recordsPerPage,
            ) {
                $student = app(UserRepository::class)->find((string) auth()->id());
                $announcements = app(AnnouncementRepository::class);

                $records = $student
                    ? $announcements->withAuthor(
                        $announcements->published('mahasiswa', (string) ($student['prodi_id'] ?? '')),
                    )
                    : collect();

                return $this->paginateRecords(
                    $records,
                    $search,
                    $sortColumn,
                    $sortDirection,
                    $page,
                    $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->weight('semibold')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('body')
                    ->label('Ringkasan')
                    ->limit(100)
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                        : '-')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('author_name')
                    ->label('Dari'),
            ])
            ->recordActions([
                Action::make('read')
                    ->label('Baca')
                    ->icon(Heroicon::Eye)
                    ->modalHeading(fn (array $record): string => (string) $record['title'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (array $record) => view('filament.portal.partials.announcement', [
                        'announcement' => $record,
                    ])),
            ])
            ->defaultSort('published_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada pengumuman');
    }
}
