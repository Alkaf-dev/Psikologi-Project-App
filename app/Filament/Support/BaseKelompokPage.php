<?php

namespace App\Filament\Support;

use App\Repositories\GroupRepository;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

abstract class BaseKelompokPage extends BaseSheetPage
{
    protected string $view = 'filament.pages.kelompok-page';

    public ?string $selectedCourseId = null;

    public ?string $selectedGroupId = null;

    protected array $searchableColumns = ['user_name', 'user_npm', 'course_name', 'role_label'];

    protected ?Collection $cachedGroupDetails = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $cachedCards = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function courseCards(): array;

    abstract protected function canManage(): bool;

    /**
     * @return array<int, Action>
     */
    protected function memberActions(): array
    {
        return [];
    }

    protected function canAccessCourse(string $courseId): bool
    {
        return $this->findCard($courseId) !== null;
    }

    protected function preferredGroupId(Collection $groups): ?string
    {
        $group = $groups->first();

        return $group ? (string) $group['id'] : null;
    }

    public function selectCourse(string $courseId): void
    {
        $courseId = trim($courseId);

        if (! $this->canAccessCourse($courseId)) {
            return;
        }

        $this->selectedCourseId = $courseId;

        $groups = $this->groupsForCourse($courseId);
        $this->selectedGroupId = $this->preferredGroupId($groups);

        $this->resetTable();
    }

    public function selectGroup(string $groupId): void
    {
        $groupId = trim($groupId);

        if (blank($this->selectedCourseId) || $groupId === '') {
            return;
        }

        $group = $this->groupsForCourse((string) $this->selectedCourseId)
            ->first(fn (array $group): bool => (string) ($group['id'] ?? '') === $groupId);

        if (! $group) {
            return;
        }

        $this->selectedGroupId = $groupId;

        $this->resetTable();
    }

    public function backToCourses(): void
    {
        $this->selectedCourseId = null;
        $this->selectedGroupId = null;

        $this->resetTable();
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
                $group = $this->selectedGroupRecord();

                if (! $group) {
                    return $this->paginateRecords(
                        collect(),
                        $search,
                        $sortColumn,
                        $sortDirection,
                        $page,
                        $recordsPerPage,
                    );
                }

                $records = app(GroupRepository::class)
                    ->membersWithUser((string) $group['id'])
                    ->map(function (array $member) use ($group): array {
                        $member['course_name'] = (string) ($group['course_name'] ?? '-');
                        $member['members_count'] = (int) ($group['members_count'] ?? 0);
                        $member['role_label'] = (string) ($member['role'] ?? 'anggota') === 'ketua'
                            ? 'Ketua'
                            : 'Anggota';

                        return $member;
                    });

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
                TextColumn::make('index')
                    ->label('No')
                    ->rowIndex()
                    ->alignCenter(),
                TextColumn::make('user_name')
                    ->label('Nama Mahasiswa/i')
                    ->weight('semibold')
                    ->description(function (array $record): string {
                        return collect([
                            trim((string) ($record['user_npm'] ?? '')),
                            trim((string) ($record['role_label'] ?? '')),
                        ])->filter()->implode(' · ');
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_name')
                    ->label('Mata Kuliah')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('members_count')
                    ->label('Jumlah Anggota')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
            ])
            ->recordActions($this->memberActions())
            ->defaultSort('user_name')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada anggota')
            ->emptyStateDescription('Pilih kelompok untuk melihat daftar anggotanya.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $course = $this->selectedCourseRecord();
        $groups = $course
            ? $this->groupsForCourse((string) $course['id'])
            : collect();

        $selectedGroup = null;

        if ($course && filled($this->selectedGroupId)) {
            $selectedGroup = $groups->first(
                fn (array $group): bool => (string) ($group['id'] ?? '') === (string) $this->selectedGroupId,
            );
        }

        return [
            'cards' => $this->cards(),
            'selectedCourse' => $course,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'canManage' => $this->canManage(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectedCourseRecord(): ?array
    {
        if (blank($this->selectedCourseId)) {
            return null;
        }

        return $this->findCard((string) $this->selectedCourseId);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function selectedGroupRecord(): ?array
    {
        if (blank($this->selectedCourseId) || blank($this->selectedGroupId)) {
            return null;
        }

        return $this->groupsForCourse((string) $this->selectedCourseId)
            ->first(fn (array $group): bool => (string) ($group['id'] ?? '') === (string) $this->selectedGroupId);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function groupsForCourse(string $courseId): Collection
    {
        if (! $this->canAccessCourse($courseId)) {
            return collect();
        }

        return $this->groupDetails()
            ->filter(fn (array $group): bool => (string) ($group['course_id'] ?? '') === $courseId)
            ->sortBy(fn (array $group): string => (string) ($group['name'] ?? ''), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findCard(string $courseId): ?array
    {
        return collect($this->cards())
            ->first(fn (array $card): bool => (string) ($card['id'] ?? '') === $courseId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function cards(): array
    {
        return $this->cachedCards ??= $this->courseCards();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function groupDetails(): Collection
    {
        return $this->cachedGroupDetails ??= app(GroupRepository::class)->withDetails();
    }

    protected function flushKelompokCaches(): void
    {
        $this->cachedCards = null;
        $this->cachedGroupDetails = null;
    }
}
