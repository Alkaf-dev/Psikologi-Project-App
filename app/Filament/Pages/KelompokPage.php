<?php

namespace App\Filament\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KelompokPage extends BaseSheetPage
{
    protected static ?string $navigationLabel = 'Kelompok';

    protected static ?string $title = 'Kelompok Mahasiswa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 5;

    protected array $searchableColumns = ['name', 'course_name', 'leader_name', 'member_names'];

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
                $records = app(GroupRepository::class)->withDetails();

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
                TextColumn::make('name')
                    ->label('Kelompok')
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_name')
                    ->label('Mata kuliah')
                    ->searchable(),
                TextColumn::make('leader_name')
                    ->label('Ketua')
                    ->searchable(),
                TextColumn::make('members_count')
                    ->label('Anggota')
                    ->badge()
                    ->color('info'),
                TextColumn::make('member_names')
                    ->label('Daftar anggota')
                    ->limit(70)
                    ->wrap(),
            ])
            ->headerActions([$this->createAction()])
            ->recordActions([$this->editAction(), $this->deleteAction()])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada kelompok');
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Kelompok')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Kelompok')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('3xl')
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $groupRepository = app(GroupRepository::class);
                $memberRepository = app(GroupMemberRepository::class);

                $memberIds = $data['member_ids'] ?? [];
                unset($data['member_ids']);

                $group = $groupRepository->create($data);

                $memberIds = $this->ensureLeaderIsMember($memberIds, (string) ($group['leader_id'] ?? ''));
                $memberRepository->syncForGroup((string) $group['id'], $memberIds, (string) ($group['leader_id'] ?? ''));

                $this->resetTable();

                Notification::make()->title('Kelompok ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading(fn (array $record): string => 'Ubah '.$record['name'])
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('3xl')
            ->fillForm(function (array $record): array {
                $record['member_ids'] = app(GroupMemberRepository::class)
                    ->forGroup((string) $record['id'])
                    ->pluck('user_id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all();

                return $record;
            })
            ->schema($this->formSchema())
            ->action(function (array $data, array $record): void {
                $groupRepository = app(GroupRepository::class);
                $memberRepository = app(GroupMemberRepository::class);

                $memberIds = $data['member_ids'] ?? [];
                unset($data['member_ids']);

                $group = $groupRepository->update((string) $record['id'], $data);

                $memberIds = $this->ensureLeaderIsMember($memberIds, (string) ($group['leader_id'] ?? ''));
                $memberRepository->syncForGroup((string) $record['id'], $memberIds, (string) ($group['leader_id'] ?? ''));

                $this->resetTable();

                Notification::make()->title('Kelompok diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus kelompok?')
            ->action(function (array $record): void {
                $memberRepository = app(GroupMemberRepository::class);

                foreach ($memberRepository->forGroup((string) $record['id']) as $member) {
                    $memberRepository->delete((string) $member['id']);
                }

                app(GroupRepository::class)->delete((string) $record['id']);

                $this->resetTable();

                Notification::make()->title('Kelompok dihapus')->success()->send();
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
            TextInput::make('name')
                ->label('Nama kelompok')
                ->required()
                ->maxLength(80)
                ->placeholder('Kelompok 1'),
            Select::make('leader_id')
                ->label('Ketua kelompok')
                ->options(fn (): array => app(UserRepository::class)->options())
                ->searchable()
                ->required(),
            CheckboxList::make('member_ids')
                ->label('Anggota kelompok')
                ->options(fn (): array => app(UserRepository::class)->options())
                ->searchable()
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('Deskripsi / topik')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<int, string>  $memberIds
     * @return array<int, string>
     */
    protected function ensureLeaderIsMember(array $memberIds, string $leaderId): array
    {
        if ($leaderId !== '' && ! in_array($leaderId, $memberIds, true)) {
            $memberIds[] = $leaderId;
        }

        return array_values(array_unique($memberIds));
    }
}
