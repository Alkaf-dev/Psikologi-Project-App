<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

class MaterialRepository extends BaseSheetRepository
{
    protected string $table = 'materials';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withCourse(?Collection $materials = null): Collection
    {
        $courses = app(CourseRepository::class);

        return ($materials ?? $this->all())
            ->map(function (array $material) use ($courses): array {
                $course = $courses->find((string) ($material['course_id'] ?? '')) ?? [];

                $material['course_name'] = (string) ($course['name'] ?? '-');
                $material['course_code'] = (string) ($course['code'] ?? '');
                $material['lecturer'] = (string) ($course['lecturer'] ?? '');

                return $material;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $student
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(array $student): Collection
    {
        $courseIds = app(CourseRepository::class)
            ->forStudent($student)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return $this->all()
            ->filter(fn (array $material): bool => in_array(
                (string) ($material['course_id'] ?? ''),
                $courseIds,
                true,
            ))
            ->values();
    }
}
