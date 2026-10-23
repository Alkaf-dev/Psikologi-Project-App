<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresStaffRole;
use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\UserRepository;
use App\Support\ExternalUrl;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JadwalPage extends BaseSheetPage
{
    use RequiresStaffRole;

    protected static ?string $navigationLabel = 'Jadwal';

    protected static ?string $title = 'Jadwal Mata Kuliah';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 2;

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
                $scheduleRepository = app(ScheduleRepository::class);
                $records = $scheduleRepository->withCourse();

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
                    ->color('gray')
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
                    ->label('Kelas')
                    ->sortable(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([$this->editAction(), $this->deleteAction()])
            ->defaultSort('day')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada jadwal');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Jadwal')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Jadwal')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('2xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $data = $this->prepareScheduleData($data);
                app(ScheduleRepository::class)->create($data);

                $this->resetTable();

                Notification::make()->title('Jadwal ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading('Ubah Jadwal')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('2xl')
            ->fillForm(fn (array $record): array => $record)
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $data = $this->prepareScheduleData($data);
                app(ScheduleRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()->title('Jadwal diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus jadwal?')
            ->action(function (array $record): void {
                app(ScheduleRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Jadwal dihapus')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            Select::make('course_id')
                ->label('Mata kuliah')
                ->options(fn (): array => app(CourseRepository::class)->options())
                ->searchable()
                ->required(),
            Select::make('day')
                ->label('Hari')
                ->options(array_combine(ScheduleRepository::days(), ScheduleRepository::days()))
                ->required(),
            TimePicker::make('start_time')
                ->label('Jam mulai')
                ->seconds(false)
                ->required(),
            TimePicker::make('end_time')
                ->label('Jam selesai')
                ->seconds(false)
                ->required(),
            Select::make('mode')
                ->label('Mode perkuliahan')
                ->options(ScheduleRepository::modes())
                ->default('offline')
                ->live()
                ->required(),
            TextInput::make('room')
                ->label('Ruangan')
                ->visible(fn (Get $get): bool => ($get('mode') ?? 'offline') !== 'online')
                ->required(fn (Get $get): bool => ($get('mode') ?? 'offline') !== 'online')
                ->maxLength(60),
            Select::make('platform')
                ->label('Platform online')
                ->options(ScheduleRepository::platforms())
                ->placeholder('Pilih platform')
                ->visible(fn (Get $get): bool => $get('mode') === 'online')
                ->required(fn (Get $get): bool => $get('mode') === 'online'),
            TextInput::make('meeting_link')
                ->label('Link (Online)')
                ->url()
                ->maxLength(500)
                ->visible(fn (Get $get): bool => $get('mode') === 'online')
                ->required(fn (Get $get): bool => $get('mode') === 'online')
                ->placeholder('https://meet.google.com/... atau tautan Zoom')
                ->helperText('Tempel tautan perkuliahan daring (Zoom, Google Meet, dll).'),
            TextInput::make('class_name')
                ->label('Kelas')
                ->required()
                ->maxLength(50)
                ->datalist(fn (): array => app(UserRepository::class)->classNames()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareScheduleData(array $data): array
    {
        $mode = ScheduleRepository::normalizeMode($data['mode'] ?? null);
        $data['mode'] = $mode;

        if ($mode === 'online') {
            $data['room'] = '';
            $data['platform'] = ScheduleRepository::normalizePlatform($data['platform'] ?? null);
            $data['meeting_link'] = trim((string) ($data['meeting_link'] ?? ''));

            return $data;
        }

        $data['platform'] = '';
        $data['meeting_link'] = '';

        return $data;
    }
}
