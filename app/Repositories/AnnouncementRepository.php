<?php

namespace App\Repositories;

use App\Support\ProdiAssignments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnnouncementRepository extends BaseSheetRepository
{
    protected string $table = 'announcements';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function published(?string $audience = null, ?string $prodiId = null): Collection
    {
        $now = now();

        return $this->all()
            ->filter(function (array $announcement) use ($audience, $prodiId): bool {
                $publishedAt = trim((string) ($announcement['published_at'] ?? ''));

                if ($publishedAt === '') {
                    return false;
                }

                if (Carbon::parse($publishedAt)->isFuture()) {
                    return false;
                }

                if ($audience !== null) {
                    $target = (string) ($announcement['audience'] ?? 'all');

                    if (! in_array($target, ['all', $audience], true)) {
                        return false;
                    }
                }

                if ($prodiId !== null && (string) ($announcement['audience'] ?? 'all') === 'mahasiswa') {
                    return ProdiAssignments::includes($announcement['prodi_ids'] ?? '', $prodiId, true);
                }

                return true;
            })
            ->sortByDesc(fn (array $announcement): string => (string) ($announcement['published_at'] ?? ''))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withAuthor(?Collection $announcements = null): Collection
    {
        $users = app(UserRepository::class);
        $courses = app(CourseRepository::class)->all()->keyBy(fn (array $c): string => (string) ($c['id'] ?? ''));

        return ($announcements ?? $this->all())
            ->map(function (array $announcement) use ($users, $courses): array {
                $announcement['author_name'] = $users->nameById((string) ($announcement['created_by'] ?? '')) ?: 'Admin';
                $audience = (string) ($announcement['audience'] ?? 'all');
                $courseId = trim((string) ($announcement['course_id'] ?? ''));
                $course = $courseId !== '' ? $courses->get($courseId) : null;
                $announcement['course_id'] = $courseId;
                $announcement['course_name'] = $course ? (string) ($course['name'] ?? '') : null;
                $announcement['course_code'] = $course ? (string) ($course['code'] ?? '') : null;

                $announcement['audience_label'] = match ($audience) {
                    'mahasiswa' => 'Mahasiswa',
                    'admin' => 'Super Admin & PJMK',
                    default => 'Semua',
                };
                $announcement['prodi_names'] = $audience === 'mahasiswa'
                    ? ProdiAssignments::label($announcement['prodi_ids'] ?? '', 'Semua Prodi')
                    : 'Publik';

                return $announcement;
            })
            ->values();
    }

    public function countByProdi(string $prodiId, bool $withTrashed = false): int
    {
        return $this->all($withTrashed)
            ->filter(fn (array $announcement): bool => ProdiAssignments::includes(
                $announcement['prodi_ids'] ?? '',
                $prodiId,
            ))
            ->count();
    }

    public function latest(int $limit = 3): Collection
    {
        return $this->published('all')->take($limit)->values();
    }
}
