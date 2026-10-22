<?php

namespace App\Repositories;

use App\Support\ProdiAssignments;
use Illuminate\Support\Collection;

class CourseRepository extends BaseSheetRepository
{
    protected string $table = 'courses';

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->all()
            ->sortBy('name')
            ->mapWithKeys(function (array $course): array {
                $code = trim((string) ($course['code'] ?? ''));
                $name = trim((string) ($course['name'] ?? 'Mata kuliah'));

                return [(string) $course['id'] => $code !== '' ? "{$code} - {$name}" : $name];
            })
            ->all();
    }

    public function labelById(?string $id): string
    {
        $course = $this->find($id);

        if (! $course) {
            return '-';
        }

        $code = trim((string) ($course['code'] ?? ''));
        $name = trim((string) ($course['name'] ?? ''));

        return $code !== '' ? "{$code} - {$name}" : $name;
    }

    public function nameById(?string $id): string
    {
        return (string) ($this->find($id)['name'] ?? '-');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forClass(string $className): Collection
    {
        return $this->all()
            ->filter(fn (array $course): bool => trim((string) ($course['class_name'] ?? '')) === $className)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $student
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(array $student): Collection
    {
        return $this->all()
            ->filter(fn (array $course): bool => $this->isAvailableForStudent($course, $student))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forProdi(string $prodiId): Collection
    {
        return $this->all()
            ->filter(fn (array $course): bool => $this->isAvailableForProdi($course, $prodiId))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  array<string, mixed>  $student
     */
    public function isAvailableForStudent(array $course, array $student): bool
    {
        return trim((string) ($course['class_name'] ?? '')) === trim((string) ($student['class_name'] ?? ''))
            && $this->isAvailableForProdi($course, (string) ($student['prodi_id'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $course
     */
    public function isAvailableForProdi(array $course, ?string $prodiId): bool
    {
        return ProdiAssignments::includes($course['prodi_ids'] ?? '', $prodiId);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function withProgramNames(?Collection $courses = null): Collection
    {
        return ($courses ?? $this->all())
            ->map(function (array $course): array {
                $course['prodi_names'] = ProdiAssignments::label($course['prodi_ids'] ?? '', 'Belum diatur');

                return $course;
            })
            ->values();
    }

    public function countByProdi(string $prodiId, bool $withTrashed = false): int
    {
        return $this->all($withTrashed)
            ->filter(fn (array $course): bool => ProdiAssignments::includes($course['prodi_ids'] ?? '', $prodiId))
            ->count();
    }

    /**
     * @return array<string, string>
     */
    public function optionsForClass(string $className): array
    {
        return $this->forClass($className)
            ->mapWithKeys(function (array $course): array {
                $code = trim((string) ($course['code'] ?? ''));
                $name = trim((string) ($course['name'] ?? 'Mata kuliah'));

                return [(string) $course['id'] => $code !== '' ? "{$code} - {$name}" : $name];
            })
            ->all();
    }
}
