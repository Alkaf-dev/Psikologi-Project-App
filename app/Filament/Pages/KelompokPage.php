<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresStaffRole;
use App\Filament\Support\BaseKelompokPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\UserRepository;
use App\Support\ProdiAssignments;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class KelompokPage extends BaseKelompokPage
{
    use RequiresStaffRole;

    protected static ?string $navigationLabel = 'Kelompok';

    protected static ?string $title = 'Kelompok Mahasiswa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 5;

    protected function canManage(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function courseCards(): array
    {
        $courses = app(CourseRepository::class);
        $groupsByCourse = $this->groupDetails()->groupBy(
            fn (array $group): string => (string) ($group['course_id'] ?? ''),
        );

        return $courses->all()
            ->sortBy(fn (array $course): string => (string) ($course['name'] ?? ''), SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (array $course) use ($groupsByCourse): array {
                $groups = $groupsByCourse->get((string) ($course['id'] ?? ''), collect());

                return [
                    'id' => (string) $course['id'],
                    'code' => (string) ($course['code'] ?? ''),
                    'name' => (string) ($course['name'] ?? 'Mata kuliah'),
                    'class_name' => (string) ($course['class_name'] ?? ''),
                    'prodi_name' => ProdiAssignments::label($course['prodi_ids'] ?? '', 'Belum diatur'),
                    'groups_count' => $groups->count(),
                    'members_count' => $groups->sum(fn (array $group): int => (int) ($group['members_count'] ?? 0)),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, Action>
     */
    protected function getActions(): array
    {
        return [$this->createAction()];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, Action>
     */
    protected function memberActions(): array
    {
        return [$this->removeMemberAction()];
    }

    protected function createAction(): Action
    {
        return Action::make('create')
            ->label('Tambah Kelompok')
            ->icon(Heroicon::Plus)
            ->modalHeading('Tambah Kelompok')
            ->modalSubmitActionLabel('Simpan')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => filled($this->selectedCourseId)
                ? ['course_id' => $this->selectedCourseId]
                : [])
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $groupRepository = app(GroupRepository::class);
                $memberRepository = app(GroupMemberRepository::class);

                $memberIds = $data['member_ids'] ?? [];
                unset($data['member_ids']);

                $group = $groupRepository->create($data);

                $memberIds = $this->ensureLeaderIsMember($memberIds, (string) ($group['leader_id'] ?? ''));
                $memberRepository->syncForGroup((string) $group['id'], $memberIds, (string) ($group['leader_id'] ?? ''));

                $this->flushKelompokCaches();
                $this->selectedCourseId = (string) $group['course_id'];
                $this->selectedGroupId = (string) $group['id'];
                $this->resetTable();

                Notification::make()->title('Kelompok ditambahkan')->success()->send();
            });
    }

    protected function editAction(): Action
    {
        return Action::make('edit')
            ->label('Ubah Kelompok')
            ->icon(Heroicon::PencilSquare)
            ->disabled(fn (): bool => blank($this->selectedGroupId))
            ->modalHeading(function (): string {
                $group = $this->selectedGroupRecord();

                return $group ? 'Ubah '.$group['name'] : 'Ubah Kelompok';
            })
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth('3xl')
            ->fillForm(function (): array {
                $group = $this->selectedGroupRecord();

                if (! $group) {
                    return [];
                }

                $group['member_ids'] = app(GroupMemberRepository::class)
                    ->forGroup((string) $group['id'])
                    ->pluck('user_id')
                    ->map(fn (mixed $id): string => (string) $id)
                    ->values()
                    ->all();

                return $group;
            })
            ->schema($this->formSchema())
            ->action(function (array $data): void {
                $group = $this->selectedGroupRecord();

                if (! $group) {
                    return;
                }

                $groupRepository = app(GroupRepository::class);
                $memberRepository = app(GroupMemberRepository::class);

                $memberIds = $data['member_ids'] ?? [];
                unset($data['member_ids']);

                $updated = $groupRepository->update((string) $group['id'], $data);

                $memberIds = $this->ensureLeaderIsMember($memberIds, (string) ($updated['leader_id'] ?? ''));
                $memberRepository->syncForGroup((string) $group['id'], $memberIds, (string) ($updated['leader_id'] ?? ''));

                $this->selectedCourseId = (string) $updated['course_id'];
                $this->selectedGroupId = (string) $updated['id'];
                $this->flushKelompokCaches();
                $this->resetTable();

                Notification::make()->title('Kelompok diperbarui')->success()->send();
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Hapus Kelompok')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->disabled(fn (): bool => blank($this->selectedGroupId))
            ->requiresConfirmation()
            ->modalHeading(fn (): string => 'Hapus '.($this->selectedGroupRecord()['name'] ?? 'kelompok').'?')
            ->modalDescription('Seluruh keanggotaan pada kelompok ini juga akan dihapus.')
            ->action(function (): void {
                $group = $this->selectedGroupRecord();

                if (! $group) {
                    return;
                }

                $memberRepository = app(GroupMemberRepository::class);

                foreach ($memberRepository->forGroup((string) $group['id']) as $member) {
                    $memberRepository->delete((string) $member['id']);
                }

                app(GroupRepository::class)->delete((string) $group['id']);

                $this->flushKelompokCaches();

                $remaining = $this->groupsForCourse((string) $group['course_id']);
                $this->selectedGroupId = $remaining->first()['id'] ?? null;

                $this->resetTable();

                Notification::make()->title('Kelompok dihapus')->success()->send();
            });
    }

    protected function addMemberAction(): Action
    {
        return Action::make('addMember')
            ->label('Tambah Anggota')
            ->icon(Heroicon::UserPlus)
            ->disabled(fn (): bool => blank($this->selectedGroupId))
            ->modalHeading(fn (): string => 'Tambah Anggota '.($this->selectedGroupRecord()['name'] ?? 'Kelompok'))
            ->modalSubmitActionLabel('Tambahkan')
            ->modalWidth('xl')
            ->schema([
                Select::make('user_ids')
                    ->label('Mahasiswa')
                    ->options(fn (): array => $this->availableStudentOptions())
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->helperText('Hanya mahasiswa aktif dari prodi yang sesuai dan belum memiliki kelompok pada mata kuliah ini.'),
            ])
            ->action(function (array $data): void {
                $group = $this->selectedGroupRecord();
                $course = $group
                    ? app(CourseRepository::class)->find((string) ($group['course_id'] ?? ''))
                    : null;

                if (! $group || ! $course) {
                    return;
                }

                $userIds = collect($data['user_ids'] ?? [])
                    ->map(fn (mixed $id): string => (string) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $users = app(UserRepository::class);
                $members = app(GroupMemberRepository::class);
                $courses = app(CourseRepository::class);
                $invalid = [];
                $wrongProdi = [];
                $alreadyGrouped = [];

                foreach ($userIds as $userId) {
                    $user = $users->find($userId);

                    if (! $user || ! $users->isStudent($user) || ! $users->isActive($user)) {
                        $invalid[] = $user['name'] ?? $userId;

                        continue;
                    }

                    if (! $courses->isAvailableForProdi($course, (string) ($user['prodi_id'] ?? ''))) {
                        $wrongProdi[] = (string) ($user['name'] ?? $userId);

                        continue;
                    }

                    if ($members->membershipForCourse($userId, (string) $course['id'])) {
                        $alreadyGrouped[] = (string) ($user['name'] ?? $userId);
                    }
                }

                $errors = [];

                if ($invalid !== []) {
                    $errors[] = 'Mahasiswa tidak valid atau tidak aktif: '.implode(', ', $invalid).'.';
                }

                if ($wrongProdi !== []) {
                    $errors[] = 'Prodi tidak sesuai: '.implode(', ', $wrongProdi).'.';
                }

                if ($alreadyGrouped !== []) {
                    $errors[] = 'Sudah memiliki kelompok pada mata kuliah ini: '.implode(', ', $alreadyGrouped).'.';
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages([
                        $this->actionErrorKey('user_ids') => implode(' ', $errors),
                    ]);
                }

                foreach ($userIds as $userId) {
                    $members->addMember((string) $group['id'], $userId);
                }

                $this->flushKelompokCaches();
                $this->resetTable();

                Notification::make()
                    ->title($userIds->count().' anggota ditambahkan')
                    ->success()
                    ->send();
            });
    }

    protected function removeMemberAction(): Action
    {
        return Action::make('removeMember')
            ->label('Keluarkan')
            ->icon(Heroicon::UserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => 'Keluarkan '.$record['user_name'].'?')
            ->action(function (array $record): void {
                $group = $this->selectedGroupRecord();

                if (! $group) {
                    return;
                }

                if ((string) ($group['leader_id'] ?? '') === (string) ($record['user_id'] ?? '')) {
                    Notification::make()
                        ->title('Ketua kelompok tidak bisa dikeluarkan')
                        ->body('Ubah ketua kelompok terlebih dahulu melalui menu Ubah Kelompok.')
                        ->danger()
                        ->send();

                    return;
                }

                app(GroupMemberRepository::class)->delete((string) $record['id']);

                $this->flushKelompokCaches();
                $this->resetTable();

                Notification::make()->title('Anggota dikeluarkan')->success()->send();
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
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('leader_id', null);
                    $set('member_ids', []);
                }),
            TextInput::make('name')
                ->label('Nama kelompok')
                ->required()
                ->maxLength(80)
                ->placeholder('Kelompok 1'),
            Select::make('leader_id')
                ->label('Ketua kelompok')
                ->options(fn (Get $get): array => $this->studentOptionsForCourse((string) ($get('course_id') ?? '')))
                ->searchable()
                ->required(),
            CheckboxList::make('member_ids')
                ->label('Anggota kelompok')
                ->options(fn (Get $get): array => $this->studentOptionsForCourse((string) ($get('course_id') ?? '')))
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
     * @return array<string, string>
     */
    protected function studentOptionsForCourse(string $courseId): array
    {
        $course = app(CourseRepository::class)->find($courseId);

        if (! $course) {
            return [];
        }

        $courses = app(CourseRepository::class);
        $users = app(UserRepository::class);

        return $users->students()
            ->filter(fn (array $user): bool => $users->isActive($user)
                && $courses->isAvailableForProdi($course, (string) ($user['prodi_id'] ?? '')))
            ->sortBy(fn (array $user): string => (string) ($user['name'] ?? ''), SORT_NATURAL | SORT_FLAG_CASE)
            ->mapWithKeys(function (array $user): array {
                $name = trim((string) ($user['name'] ?? 'Tanpa nama'));
                $npm = trim((string) ($user['npm'] ?? ''));

                return [(string) $user['id'] => $npm !== '' ? "{$name} ({$npm})" : $name];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function availableStudentOptions(): array
    {
        $group = $this->selectedGroupRecord();

        if (! $group) {
            return [];
        }

        $courseId = (string) ($group['course_id'] ?? '');
        $groupIds = app(GroupRepository::class)
            ->forCourse($courseId)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $takenIds = app(GroupMemberRepository::class)
            ->all()
            ->filter(fn (array $member): bool => in_array((string) ($member['group_id'] ?? ''), $groupIds, true))
            ->pluck('user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->all();

        return collect($this->studentOptionsForCourse($courseId))
            ->except($takenIds)
            ->all();
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

    protected function actionErrorKey(string $field): string
    {
        $statePath = $this->getMountedActionSchema()?->getStatePath();

        return $statePath ? "{$statePath}.{$field}" : $field;
    }
}
