<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Concerns\RequiresStudentRole;
use App\Filament\Support\BaseKelompokPage;
use App\Repositories\CourseRepository;
use App\Repositories\GroupMemberRepository;
use App\Repositories\GroupRepository;
use App\Repositories\UserRepository;
use App\Support\ProdiAssignments;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class KelompokPage extends BaseKelompokPage
{
    use RequiresStudentRole;

    protected static ?string $slug = 'kelompok-saya';

    protected static ?string $navigationLabel = 'Kelompok Saya';

    protected static ?string $title = 'Kelompok Saya';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    protected function canManage(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    protected function canAccessGroup(array $group): bool
    {
        return in_array(
            (string) ($group['id'] ?? ''),
            app(GroupMemberRepository::class)->groupIdsForUser((string) auth()->id()),
            true,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function courseCards(): array
    {
        $student = app(UserRepository::class)->find((string) auth()->id());

        if (! $student) {
            return [];
        }

        $groups = app(GroupRepository::class);
        $courses = app(CourseRepository::class);
        $details = $groups->withDetails($groups->forStudent($student));

        return $details
            ->groupBy(fn (array $group): string => (string) ($group['course_id'] ?? ''))
            ->map(function (Collection $courseGroups, string $courseId) use ($courses): ?array {
                $course = $courses->find($courseId);

                if (! $course) {
                    return null;
                }

                return [
                    'id' => (string) $course['id'],
                    'code' => (string) ($course['code'] ?? ''),
                    'name' => (string) ($course['name'] ?? 'Mata kuliah'),
                    'class_name' => (string) ($course['class_name'] ?? ''),
                    'prodi_name' => ProdiAssignments::label($course['prodi_ids'] ?? '', 'Belum diatur'),
                    'groups_count' => $courseGroups->count(),
                    'members_count' => $courseGroups->sum(fn (array $group): int => (int) ($group['members_count'] ?? 0)),
                ];
            })
            ->filter()
            ->sortBy(fn (array $card): string => (string) $card['name'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    protected function preferredGroupId(Collection $groups): ?string
    {
        $groupIds = app(GroupMemberRepository::class)->groupIdsForUser((string) auth()->id());

        $ownGroup = $groups->first(
            fn (array $group): bool => in_array((string) ($group['id'] ?? ''), $groupIds, true),
        );

        return $ownGroup ? (string) $ownGroup['id'] : parent::preferredGroupId($groups);
    }
}
