<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Repositories\AnnouncementRepository;
use App\Repositories\CourseRepository;
use App\Repositories\StudyProgramRepository;
use App\Repositories\UserRepository;
use App\Support\ApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class StudyProgramController extends ApiController
{
    public function index(Request $request, StudyProgramRepository $programs): JsonResponse
    {
        $items = $this->search($programs->all(), $request, ['code', 'name', 'degree']);
        $items = $this->filter($items, $request, 'active', 'active');
        $items = $this->sort($items, $request, ['code', 'name', 'degree', 'created_at'], 'name');

        return $this->paginated(
            $items,
            $request,
            fn (array $program): array => ApiPresenter::program($program),
        );
    }

    public function show(string $id, StudyProgramRepository $programs): JsonResponse
    {
        return $this->data(ApiPresenter::program(
            $this->found($programs->find($id), 'Program studi tidak ditemukan.'),
        ));
    }

    public function store(Request $request, StudyProgramRepository $programs): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'degree' => ['required', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $data = $this->prepare($data);
        $this->validateUnique($programs, $data);

        $program = $programs->create($data);

        return response()->json([
            'data' => ApiPresenter::program($program),
            'message' => 'Program studi berhasil ditambahkan.',
        ], 201);
    }

    public function update(Request $request, string $id, StudyProgramRepository $programs): JsonResponse
    {
        $program = $this->found($programs->find($id), 'Program studi tidak ditemukan.');

        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:30'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'degree' => ['sometimes', 'required', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $data = $this->prepare(array_merge($program, $data));
        $this->validateUnique($programs, $data, $id);

        $updated = $programs->update($id, [
            'code' => $data['code'],
            'name' => $data['name'],
            'degree' => $data['degree'],
            'active' => $data['active'],
        ]);

        return $this->data(ApiPresenter::program($updated));
    }

    public function destroy(
        string $id,
        StudyProgramRepository $programs,
        UserRepository $users,
        CourseRepository $courses,
        AnnouncementRepository $announcements,
    ): Response {
        $this->found($programs->find($id), 'Program studi tidak ditemukan.');

        $usage = [];

        if (($count = $users->countByProdi($id, true)) > 0) {
            $usage[] = "{$count} akun";
        }

        if (($count = $courses->countByProdi($id, true)) > 0) {
            $usage[] = "{$count} mata kuliah";
        }

        if (($count = $announcements->countByProdi($id, true)) > 0) {
            $usage[] = "{$count} pengumuman";
        }

        if ($usage !== []) {
            abort(422, 'Prodi masih dipakai oleh '.implode(', ', $usage).'. Nonaktifkan Prodi sebagai gantinya.');
        }

        $programs->delete($id);

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data): array
    {
        return [
            'code' => mb_strtolower(trim((string) ($data['code'] ?? ''))),
            'name' => trim((string) ($data['name'] ?? '')),
            'degree' => trim((string) ($data['degree'] ?? '')),
            'active' => filter_var($data['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateUnique(StudyProgramRepository $programs, array $data, ?string $exceptId = null): void
    {
        $errors = [];

        if ($programs->codeExists((string) $data['code'], $exceptId)) {
            $errors['code'] = 'Kode Prodi sudah digunakan.';
        }

        if ($programs->nameExists((string) $data['name'], $exceptId)) {
            $errors['name'] = 'Nama Prodi sudah digunakan.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
