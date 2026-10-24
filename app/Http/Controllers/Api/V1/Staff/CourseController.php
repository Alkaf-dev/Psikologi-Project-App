<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\CourseRepository;
use App\Support\ApiPresenter;
use App\Support\ProdiAssignments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseController extends ApiController
{
    public function index(Request $request, CourseRepository $courses): JsonResponse
    {
        $items = $courses->withProgramNames();
        $items = $this->search($items, $request, [
            'code', 'name', 'lecturer', 'semester', 'class_name', 'prodi_names',
        ]);
        $items = $this->filter($items, $request, 'class_name', 'class_name');
        $items = $this->filter($items, $request, 'semester', 'semester');
        $items = $this->filterProdi($items, $request);
        $items = $this->sort($items, $request, ['code', 'name', 'credits', 'semester', 'class_name'], 'name');

        return $this->paginated(
            $items,
            $request,
            fn (array $course): array => ApiPresenter::course($course),
        );
    }

    public function show(string $id, CourseRepository $courses): JsonResponse
    {
        $course = $this->found($courses->find($id), 'Mata kuliah tidak ditemukan.');

        return $this->data(ApiPresenter::course(
            $courses->withProgramNames(collect([$course]))->first(),
        ));
    }

    public function store(Request $request, CourseRepository $courses): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'credits' => ['required', 'integer', 'min:1', 'max:8'],
            'lecturer' => ['required', 'string', 'max:150'],
            'semester' => ['required', 'string', 'max:20'],
            'class_name' => ['required', 'string', 'max:50'],
            'prodi_ids' => ['required', 'array', 'min:1'],
            'prodi_ids.*' => ['required', 'string'],
            'meeting_link' => ['nullable', 'url:http,https', 'max:500'],
            'description' => ['nullable', 'string'],
        ]);

        $this->validatePrograms($data['prodi_ids']);

        $course = $courses->create($this->prepare($data));

        return response()->json([
            'data' => ApiPresenter::course($course),
            'message' => 'Mata kuliah berhasil ditambahkan.',
        ], 201);
    }

    public function update(Request $request, string $id, CourseRepository $courses): JsonResponse
    {
        $course = $this->found($courses->find($id), 'Mata kuliah tidak ditemukan.');

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:30'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'credits' => ['sometimes', 'required', 'integer', 'min:1', 'max:8'],
            'lecturer' => ['sometimes', 'required', 'string', 'max:150'],
            'semester' => ['sometimes', 'required', 'string', 'max:20'],
            'class_name' => ['sometimes', 'required', 'string', 'max:50'],
            'prodi_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'prodi_ids.*' => ['required', 'string'],
            'meeting_link' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $merged = array_merge($course, $data);
        $programIds = ProdiAssignments::ids($merged['prodi_ids'] ?? []);

        if ($programIds === []) {
            throw ValidationException::withMessages(['prodi_ids' => 'Pilih minimal satu Prodi.']);
        }

        $this->validatePrograms($programIds);

        $updated = $courses->update($id, $this->prepare($merged));

        return $this->data(ApiPresenter::course($updated));
    }

    public function destroy(string $id, CourseRepository $courses): Response
    {
        $this->found($courses->find($id), 'Mata kuliah tidak ditemukan.');
        $courses->delete($id);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return [
            'code' => trim((string) ($data['code'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'credits' => (int) ($data['credits'] ?? 0),
            'lecturer' => trim((string) ($data['lecturer'] ?? '')),
            'semester' => trim((string) ($data['semester'] ?? '')),
            'class_name' => trim((string) ($data['class_name'] ?? '')),
            'prodi_ids' => ProdiAssignments::csv($data['prodi_ids'] ?? []),
            'meeting_link' => trim((string) ($data['meeting_link'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
        ];
    }

    /**
     * @param  array<int, mixed>  $programIds
     */
    protected function validatePrograms(array $programIds): void
    {
        $invalid = ProdiAssignments::invalidIds($programIds);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'prodi_ids' => 'Pilihan Prodi tidak valid.',
            ]);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterProdi(Collection $items, Request $request): Collection
    {
        $prodiId = trim((string) $request->query('prodi_id', ''));

        if ($prodiId === '') {
            return $items;
        }

        return $items
            ->filter(fn (array $course): bool => ProdiAssignments::includes(
                $course['prodi_ids'] ?? '',
                $prodiId,
            ))
            ->values();
    }
}
