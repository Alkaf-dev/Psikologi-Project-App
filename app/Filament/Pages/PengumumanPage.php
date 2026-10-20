<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\AnnouncementRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class PengumumanPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $title = 'Pengumuman';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 1;

    protected array $searchableColumns = ['title', 'body', 'audience_label', 'author_name'];

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
                $records = app(AnnouncementRepository::class)
                    ->withAuthor()
                    ->sortByDesc('published_at')
                    ->values();

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
                    ->label('Isi')
                    ->limit(70)
                    ->wrap(),
                TextColumn::make('audience_label')
                    ->label('Audiens')
                    ->badge()
                    ->color('info'),
                TextColumn::make('published_at')
                    ->label('Terbit')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? Carbon::parse($state)->translatedFormat('d M Y H:i')
                        : 'Draft')
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label('Penulis')
                    ->placeholder('-'),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([
                $this->readAction(),
                $this->editAction(),
                $this->deleteAction(),
            ])
            ->defaultSort('published_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada pengumuman');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Pengumuman')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Pengumuman')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('3xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $data['created_by'] = (string) auth()->id();

                app(AnnouncementRepository::class)->create($data);

                $this->resetTable();

                Notification::make()->title('Pengumuman ditambahkan')->success()->send();
            });
    }

    protected function readAction(): Action
    {
        return Action::make('read')
            ->label('Baca')
            ->icon(Heroicon::Eye)
            ->modalHeading(fn (array $record): string => (string) $record['title'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (array $record) => view('filament.partials.announcement-body', [
                'body' => (string) ($record['body'] ?? ''),
            ]));
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading('Ubah Pengumuman')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('3xl')
            ->fillForm(fn (array $record): array => $this->normalizeAnnouncement($record))
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                app(AnnouncementRepository::class)->update((string) $record['id'], $data);

                $this->resetTable();

                Notification::make()->title('Pengumuman diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus pengumuman?')
            ->action(function (array $record): void {
                app(AnnouncementRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Pengumuman dihapus')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    protected function formSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(180),
            Select::make('audience')
                ->label('Audiens')
                ->options([
                    'all' => 'Semua pengunjung',
                    'mahasiswa' => 'Mahasiswa',
                    'admin' => 'Super Admin & PJMK',
                ])
                ->default('all')
                ->required(),
            DateTimePicker::make('published_at')
                ->label('Waktu terbit')
                ->seconds(false)
                ->default(now())
                ->required(),
            Textarea::make('body')
                ->label('Isi pengumuman')
                ->rows(7)
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalizeAnnouncement(array $record): array
    {
        $record['published_at'] = filled($record['published_at'] ?? null)
            ? Carbon::parse($record['published_at'])->format('Y-m-d H:i:s')
            : null;

        return $record;
    }
}
