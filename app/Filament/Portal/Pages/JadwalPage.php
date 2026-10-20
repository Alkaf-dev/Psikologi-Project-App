<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
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

    protected array $searchableColumns = ['day', 'course_name', 'room', 'class_name'];

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

                $records = app(ScheduleRepository::class)->withCourse(
                    app(ScheduleRepository::class)->forClass((string) ($student['class_name'] ?? '')),
                );

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
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Selesai'),
                TextColumn::make('course_name')
                    ->label('Mata kuliah')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('course_lecturer')
                    ->label('Dosen'),
                TextColumn::make('room')
                    ->label('Ruangan'),
                TextColumn::make('class_name')
                    ->label('Kelas'),
            ])
            ->defaultSort('day')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada jadwal')
            ->emptyStateDescription('Jadwal untuk kelas Anda belum diinput oleh admin.');
    }
}
