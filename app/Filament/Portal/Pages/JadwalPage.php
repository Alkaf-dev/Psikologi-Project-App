<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Support\ExternalUrl;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JadwalPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Jadwal Kuliah';

    protected static ?string $title = 'Jadwal Kuliah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['day', 'course_name', 'room', 'class_name', 'mode_label', 'location_label'];

    public function pageMascot(): ?string
    {
        return 'berjalan';
    }

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
                $schedules = app(ScheduleRepository::class);

                $records = $student
                    ? $schedules->withCourse($schedules->forStudent($student))
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
                TextColumn::make('day')
                    ->label('Hari')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->alignCenter(),
                TextColumn::make('course_name')
                    ->label('Mata kuliah')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('course_lecturer')
                    ->label('Dosen'),
                TextColumn::make('mode_label')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (array $record): string => ($record['mode'] ?? 'offline') === 'online' ? 'info' : 'gray')
                    ->alignCenter(),
                TextColumn::make('location_label')
                    ->label('Ruang / Online')
                    ->weight('semibold')
                    ->url(fn (array $record): ?string => ($record['mode'] ?? 'offline') === 'online' && filled($record['meeting_link'] ?? null) ? (string) $record['meeting_link'] : null, true)
                    ->color(fn (array $record): ?string => ($record['mode'] ?? 'offline') === 'online' ? 'primary' : null),
                TextColumn::make('meeting_link')
                    ->label('Link (Online)')
                    ->formatStateUsing(fn (?string $state): string => ExternalUrl::http($state) !== null ? 'Masuk' : '-')
                    ->url(fn (?string $state): ?string => ExternalUrl::http($state))
                    ->openUrlInNewTab()
                    ->color(fn (?string $state): ?string => ExternalUrl::http($state) !== null ? 'primary' : null)
                    ->tooltip(fn (?string $state): ?string => ExternalUrl::http($state) !== null ? 'Buka tautan perkuliahan di tab baru' : null)
                    ->alignCenter(),
                TextColumn::make('class_name')
                    ->label('Kelas'),
            ])
            ->defaultSort('day')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada jadwal')
            ->emptyStateDescription('Jadwal untuk Prodi dan kelas Anda belum diinput oleh admin.');
    }
}
