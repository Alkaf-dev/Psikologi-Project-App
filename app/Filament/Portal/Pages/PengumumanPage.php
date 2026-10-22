<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Support\BaseSheetPage;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\UserRepository;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PengumumanPage extends BaseSheetPage
{
    protected string $view = 'filament.portal.pages.pengumuman';

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $title = 'Pengumuman';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Informasi';

    protected static ?int $navigationSort = 1;

    public string $selectedFilter = 'all';

    protected ?Collection $cachedAllAnnouncements = null;

    protected array $searchableColumns = ['title', 'course_name', 'body', 'author_name'];

    public function pageMascot(): ?string
    {
        return 'menunjuk';
    }

    public function selectFilter(string $filter): void
    {
        $this->selectedFilter = $filter;
        $this->resetTable();
    }

    public function resetTable(): void
    {
        $this->cachedAllAnnouncements = null;
        $this->resetPage();
        parent::resetTable();
    }

    protected function allAnnouncements(): Collection
    {
        if ($this->cachedAllAnnouncements !== null) {
            return $this->cachedAllAnnouncements;
        }

        $student = app(UserRepository::class)->find((string) auth()->id());
        $announcements = app(AnnouncementRepository::class);

        return $this->cachedAllAnnouncements = $student
            ? $announcements->withAuthor(
                $announcements->published('mahasiswa', (string) ($student['prodi_id'] ?? '')),
            )->values()
            : collect();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $all = $this->allAnnouncements();
        $student = app(UserRepository::class)->find((string) auth()->id());
        $courseRepo = app(CourseRepository::class);
        $allCourses = $courseRepo->all()->keyBy(fn (array $c): string => (string) ($c['id'] ?? ''));

        $studentCourses = $student ? $courseRepo->forStudent($student) : collect();
        if ($studentCourses->isEmpty()) {
            $studentCourses = $courseRepo->all();
        }

        $relevantCourseIds = $studentCourses->pluck('id')
            ->merge($all->pluck('course_id')->filter())
            ->unique()
            ->filter();

        $courses = $relevantCourseIds->map(fn ($id) => $allCourses->get((string) $id))
            ->filter()
            ->sortBy('name');

        $courseFilters = $courses->map(function (array $course) use ($all): array {
            $id = (string) ($course['id'] ?? '');
            $count = $all->filter(fn (array $a): bool => (string) ($a['course_id'] ?? '') === $id)->count();

            return [
                'id' => $id,
                'name' => (string) ($course['name'] ?? 'Mata Kuliah'),
                'code' => (string) ($course['code'] ?? ''),
                'count' => $count,
            ];
        })->values()->all();

        return [
            'selectedFilter' => $this->selectedFilter,
            'totalCount' => $all->count(),
            'publicCount' => $all->filter(fn (array $a): bool => empty($a['course_id']))->count(),
            'courseFilters' => $courseFilters,
        ];
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
                $records = $this->allAnnouncements();

                if ($this->selectedFilter === 'public') {
                    $records = $records->filter(fn (array $a): bool => empty($a['course_id']))->values();
                } elseif ($this->selectedFilter !== 'all') {
                    $records = $records->filter(fn (array $a): bool => (string) ($a['course_id'] ?? '') === $this->selectedFilter)->values();
                }

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
                TextColumn::make('course_name')
                    ->label('Mata Kuliah')
                    ->placeholder('Publik')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) && $state !== '-' ? 'primary' : 'gray')
                    ->alignCenter()
                    ->searchable(),
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
