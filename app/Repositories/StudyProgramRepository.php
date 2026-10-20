<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

class StudyProgramRepository extends BaseSheetRepository
{
    protected string $table = 'study_programs';

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function active(): Collection
    {
        return $this->all()
            ->filter(fn (array $program): bool => $this->isActive($program))
            ->sortBy('name')
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function activeOptions(): array
    {
        return $this->active()
            ->mapWithKeys(fn (array $program): array => [
                (string) $program['id'] => $this->label($program),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function allOptions(): array
    {
        return $this->all()
            ->sortBy('name')
            ->mapWithKeys(fn (array $program): array => [
                (string) $program['id'] => $this->label($program).($this->isActive($program) ? '' : ' (nonaktif)'),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function nameMap(): array
    {
        return $this->all()
            ->mapWithKeys(fn (array $program): array => [
                (string) $program['id'] => (string) ($program['name'] ?? ''),
            ])
            ->all();
    }

    public function nameById(?string $id): string
    {
        $program = $this->find($id);

        return $program ? (string) ($program['name'] ?? '') : '';
    }

    public function findByCode(string $code): ?array
    {
        $code = $this->normalizeCode($code);

        if ($code === '') {
            return null;
        }

        return $this->all()->first(
            fn (array $program): bool => $this->normalizeCode((string) ($program['code'] ?? '')) === $code,
        );
    }

    public function codeExists(string $code, ?string $exceptId = null): bool
    {
        $code = $this->normalizeCode($code);

        if ($code === '') {
            return false;
        }

        return $this->all()->contains(function (array $program) use ($code, $exceptId): bool {
            if ($exceptId !== null && (string) ($program['id'] ?? '') === $exceptId) {
                return false;
            }

            return $this->normalizeCode((string) ($program['code'] ?? '')) === $code;
        });
    }

    public function nameExists(string $name, ?string $exceptId = null): bool
    {
        $name = mb_strtolower(trim($name));

        if ($name === '') {
            return false;
        }

        return $this->all()->contains(function (array $program) use ($name, $exceptId): bool {
            if ($exceptId !== null && (string) ($program['id'] ?? '') === $exceptId) {
                return false;
            }

            return mb_strtolower(trim((string) ($program['name'] ?? ''))) === $name;
        });
    }

    public function isActive(array $program): bool
    {
        return in_array(
            mb_strtolower(trim((string) ($program['active'] ?? ''))),
            ['1', 'true', 'yes', 'ya'],
            true,
        );
    }

    protected function label(array $program): string
    {
        $name = trim((string) ($program['name'] ?? 'Tanpa nama'));
        $degree = trim((string) ($program['degree'] ?? ''));

        return $degree !== '' ? "{$degree} {$name}" : $name;
    }

    protected function normalizeCode(string $code): string
    {
        return mb_strtolower(trim($code));
    }
}
