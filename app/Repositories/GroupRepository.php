<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

class GroupRepository extends BaseSheetRepository
{
    protected string $table = 'groups';

    /**
     * @param  array<string, mixed>  $student
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(array $student): Collection
    {
        $groupIds = app(GroupMemberRepository::class)
            ->groupIdsForUser((string) ($student['id'] ?? ''));

        $courseIds = app(CourseRepository::class)
            ->forProdi((string) ($student['prodi_id'] ?? ''))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return $this->all()
            ->filter(fn (array $group): bool => in_array((string) ($group['id'] ?? ''), $groupIds, true)
                && in_array((string) ($group['course_id'] ?? ''), $courseIds, true))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withDetails(?Collection $groups = null): Collection
    {
        $courses = app(CourseRepository::class);
        $users = app(UserRepository::class);
        $members = app(GroupMemberRepository::class);

        return ($groups ?? $this->all())
            ->map(function (array $group) use ($courses, $users, $members): array {
                $group['course_name'] = $courses->nameById((string) ($group['course_id'] ?? ''));
                $group['course_code'] = (string) ($courses->find((string) ($group['course_id'] ?? ''))['code'] ?? '');
                $group['leader_name'] = $users->nameById((string) ($group['leader_id'] ?? ''));
                $group['members_count'] = $members->forGroup((string) $group['id'])->count();
                $group['member_names'] = $this->memberNames((string) $group['id']);

                return $group;
            });
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $courses = app(CourseRepository::class);

        return $this->all()
            ->sortBy('name')
            ->mapWithKeys(function (array $group) use ($courses): array {
                $label = trim((string) ($group['name'] ?? 'Kelompok'));
                $course = $courses->nameById((string) ($group['course_id'] ?? ''));

                return [(string) $group['id'] => "{$label} - {$course}"];
            })
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function membersWithUser(string $groupId): Collection
    {
        $users = app(UserRepository::class);

        return app(GroupMemberRepository::class)
            ->forGroup($groupId)
            ->map(function (array $member) use ($users): array {
                $user = $users->find((string) ($member['user_id'] ?? ''));

                $member['user_name'] = $user['name'] ?? 'Tanpa nama';
                $member['user_npm'] = $user['npm'] ?? '';

                return $member;
            })
            ->values();
    }

    public function memberNames(string $groupId): string
    {
        return $this->membersWithUser($groupId)
            ->pluck('user_name')
            ->filter()
            ->implode(', ');
    }

    public function forCourse(string $courseId): Collection
    {
        return $this->all()
            ->filter(fn (array $group): bool => (string) ($group['course_id'] ?? '') === $courseId)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forCourseWithDetails(string $courseId): Collection
    {
        return $this->withDetails($this->forCourse($courseId))
            ->sortBy(fn (array $group): string => (string) ($group['name'] ?? ''), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function optionsForCourse(string $courseId): array
    {
        return $this->forCourseWithDetails($courseId)
            ->mapWithKeys(fn (array $group): array => [
                (string) $group['id'] => (string) ($group['name'] ?? 'Kelompok'),
            ])
            ->all();
    }
}
