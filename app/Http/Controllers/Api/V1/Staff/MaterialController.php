<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\CourseRepository;
use App\Repositories\MaterialRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MaterialController extends ApiController
{
    public function index(Request $request, MaterialRepository $materials): JsonResponse
    {
        $items = $materials->withCourse($materials->all());
        $items = $this->search($items, $request, [
            'title', 'description', 'course_name', 'course_code',
        ]);
        $items = $this->filter($items, $request, 'course_id', 'course_id');
        $items = $this->sort($items, $request, ['title', 'meeting_number', 'created_at'], 'title');

        return $this->paginated(
            $items,
            $request,
            fn (array $material): array => ApiPresenter::material($material),
        );
    }

    public function show(string $id, MaterialRepository $materials): JsonResponse
    {
        $material = $this->found($materials->find($id), 'Materi tidak ditemukan.');

        return $this->data(ApiPresenter::material(
            $materials->withCourse(collect([$material]))->first(),
        ));
    }

    public function store(
        Request $request,
        MaterialRepository $materials,
        CourseRepository $courses,
    ): JsonResponse {
        $data = $request->validate($this->rules());
        $this->validateCourse($data, $courses);

        $material = $materials->create($this->prepare($data) + [
            'created_by' => (string) auth('api')->id(),
        ]);

        return response()->json([
            'data' => ApiPresenter::material(
                $materials->withCourse(collect([$material]))->first(),
            ),
            'message' => 'Materi berhasil ditambahkan.',
        ], 201);
    }

    public function update(
        Request $request,
        string $id,
        MaterialRepository $materials,
        CourseRepository $courses,
    ): JsonResponse {
        $material = $this->found($materials->find($id), 'Materi tidak ditemukan.');

        $data = $request->validate($this->rules(true));
        $merged = array_merge($material, $data);
        $this->validateCourse($merged, $courses);

        $updated = $materials->update($id, $this->prepare($merged));

        return $this->data(ApiPresenter::material(
            $materials->withCourse(collect([$updated]))->first(),
        ));
    }

    public function destroy(string $id, MaterialRepository $materials): Response
    {
        $this->found($materials->find($id), 'Materi tidak ditemukan.');
        $materials->delete($id);

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'course_id' => [$required, 'string'],
            'title' => [$required, 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'url' => [$required, 'url:http,https', 'max:500'],
            'meeting_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateCourse(array $data, CourseRepository $courses): void
    {
        if ($courses->find(trim((string) ($data['course_id'] ?? ''))) === null) {
            throw ValidationException::withMessages([
                'course_id' => 'Pilih mata kuliah yang valid.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return [
            'course_id' => trim((string) ($data['course_id'] ?? '')),
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'url' => trim((string) ($data['url'] ?? '')),
            'meeting_number' => $data['meeting_number'] ?? '',
        ];
    }
}
