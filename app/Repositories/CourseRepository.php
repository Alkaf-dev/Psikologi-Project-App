<?php

namespace App\Repositories;

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
