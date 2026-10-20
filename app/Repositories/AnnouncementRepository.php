<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnnouncementRepository extends BaseSheetRepository
{
    protected string $table = 'announcements';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function published(?string $audience = null): Collection
    {
        $now = now();

        return $this->all()
            ->filter(function (array $announcement) use ($audience): bool {
                $publishedAt = trim((string) ($announcement['published_at'] ?? ''));

                if ($publishedAt === '') {
                    return false;
                }

                if (Carbon::parse($publishedAt)->isFuture()) {
                    return false;
                }

                if ($audience === null) {
                    return true;
                }

                $target = (string) ($announcement['audience'] ?? 'all');

                return in_array($target, ['all', $audience], true);
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

        return ($announcements ?? $this->all())
            ->map(function (array $announcement) use ($users): array {
                $announcement['author_name'] = $users->nameById((string) ($announcement['created_by'] ?? '')) ?: 'Admin';
                $announcement['audience_label'] = match ((string) ($announcement['audience'] ?? 'all')) {
                    'mahasiswa' => 'Mahasiswa',
                    'admin' => 'Super Admin & PJMK',
                    default => 'Semua',
                };

                return $announcement;
            })
            ->values();
    }

    public function latest(int $limit = 3): Collection
    {
        return $this->published('all')->take($limit)->values();
    }
}
